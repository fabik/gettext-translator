<?php

/**
 * Gettext translator.
 * Copyright (c) 2011 Jan-Sebastian Fabik (http://fabik.org)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use Nette\Object,
	Nette\FileNotFoundException,
	Nette\InvalidStateException,
	Nette\Localization\ITranslator,
	Nette\Utils\Strings;



/**
 * Gettext translator.
 *
 * This solution is partially based on:
 *   - Zend_Translate_Adapter_Gettext (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com), New BSD License
 *   - former GettextTranslator (c) 2009 Roman Sklenář (http://romansklenar.cz), New BSD License
 *
 * @author     Jan-Sebastian Fabik
 * @copyright  Copyright (c) 2011 Jan-Sebastian Fabik (http://fabik.org)
 * @license    MIT License
 * @package    GettextTranslator
 * @version    1.0
 * @example    http://addons.nette.org/gettext-translator
 */
class GettextTranslator extends Object implements ITranslator
{
	/** default Plural-Forms meta */
	const DEFAULT_PLURAL_FORMS = 'nplurals=2; plural=n!=1;';

	/** @var string|NULL */
	public $locale;

	/** @var bool */
	private $endian = FALSE;

	/** @var resource */
	private $handle;

	/** @var array of array of string */
	protected $dictionary = array();

	/** @var array of string */
	protected $meta = array();

	/** @var string */
	protected $plurals = NULL;

	/** @var callback */
	private $pluralscb = NULL;



	/**
	 * @param  string
	 * @param  string|NULL
	 */
	public function __construct($file, $locale = NULL)
	{
		$this->locale = $locale;
		$this->buildDictionary($file);
	}



	/**
	 * Translates the given string.
	 * @param  string  translation string
	 * @param  int     count
	 * @return string
	 */
	public function translate($message, $count = 1)
	{
		$message = (string) $message;
		if (!empty($message) && isset($this->dictionary[$message])) {
			$word = $this->dictionary[$message];
			$plural = is_int($count) ? (int) call_user_func($this->pluralscb, $count) : 0;
			$message = isset($word[$plural]) ? $word[$plural] : $word[0];
		}

		if (func_num_args() > 1) {
			$args = func_get_args();
			array_shift($args);
			$message = vsprintf($message, $args);
		}
		return $message;
	}



	/**
	 * Load translation data (MO file reader) and builds the dictionary.
	 * @param  string  MO file to add
	 * @throws Nette\InvalidStateException
	 * @return void
	 */
	private function buildDictionary($file)
	{
		$this->endian = FALSE;
		$this->handle = @fopen($file, 'rb'); // @ - file may not exist
		if (!$this->handle) {
			throw new Nette\FileNotFoundException("Cannot open translation file '$file'.");
		}
		if (@filesize($file) < 10) { // @ - file may not exist
			throw new InvalidStateException("'$file' is not a gettext file.");
		}

		// get endian
		$input = $this->readMoData(1);
		switch ($input[1]) {
		case (int) 0x950412de:
			$this->endian = FALSE;
			break;
		case (int) 0xde120495:
			$this->endian = TRUE;
			break;
		default:
			throw new InvalidStateException("'$file' is not a gettext file.");
		}

		// read input data
		$input = $this->readMoData(4);

		// check revision
		if ($input[1] !== 0) {
			throw new InvalidStateException("'$file' is not a gettext file.");
		}

		// number of strings
		$total = $input[2];

		// offset of original strings array
		$originalOffset = $input[3];

		// offset of translation strings array
		$translationOffset = $input[4];

		// fill the original table
		fseek($this->handle, $originalOffset);
		$origOffsets = $this->readMoData(2 * $total);
		fseek($this->handle, $translationOffset);
		$transOffsets = $this->readMoData(2 * $total);

		for ($count = 0; $count < $total; $count++) {
			if ($origOffsets[$count * 2 + 1] !== 0) {
				fseek($this->handle, $origOffsets[$count * 2 + 2]);
				$original = fread($this->handle, $origOffsets[$count * 2 + 1]);
			} else {
				$original = '';
			}

			if ($transOffsets[$count * 2 + 1] !== 0) {
				fseek($this->handle, $transOffsets[$count * 2 + 2]);
				$tr = fread($this->handle, $transOffsets[$count * 2 + 1]);
				if ($original === '') {
					$this->generateMeta($tr);
					continue;
				}

				$original = substr($original, 0, strcspn($original, "\0"));
				$this->dictionary[$original] = explode("\0", $tr);
			}
		}

		fclose($this->handle);

		// parse plural forms
		$meta = isset($this->meta['Plural-Forms']) ? $this->meta['Plural-Forms'] : static::DEFAULT_PLURAL_FORMS;
		$this->plurals = self::parsePluralForms($meta);
		if (!$this->createPluralsFunction()) {
			throw new InvalidStateException("Invalid Plural-Forms meta provided in file '$file'.");
		}
	}



	/**
	 * Read values from the MO file.
	 * @param  int  number of 32-bit integers to read
	 * @return array
	 */
	private function readMoData($n)
	{
		$data = fread($this->handle, 4 * $n);
		return $this->endian ? unpack('N' . $n, $data) : unpack('V' . $n, $data);
	}



	/**
	 * Generates meta information about dictionary.
	 * @param  string
	 * @return void
	 */
	private function generateMeta($s)
	{
		foreach (explode("\n", $s) as $meta) {
			$tmp = explode(': ', $meta, 2);
			if (isset($tmp[1])) {
				$this->meta[trim($tmp[0])] = $tmp[1];
			}
		}
	}



	/**
	 * @return bool
	 */
	private function createPluralsFunction()
	{
		return $this->plurals !== NULL && (bool) $this->pluralscb = create_function('$n', $this->plurals);
	}



	/**
	 * @return array
	 */
	public function __sleep()
	{
		return array('locale', 'dictionary', 'meta', 'plurals');
	}



	/**
	 * @return void
	 */
	public function __wakeup()
	{
		if (!$this->createPluralsFunction()) {
			throw new InvalidStateException('Invalid Plural-Forms provided.');
		}
	}



	/**
	 * Converts C compatible Plural-Forms to a PHP function.
	 * @param  string
	 * @return string|NULL
	 */
	private static function parsePluralForms($meta)
	{
		$matches = Strings::match($meta, '#^\s*nplurals\s*=\s*\d+\s*;\s+plural\s*=\s*(.+)\s*;\s*$#');
		if (!$matches) {
			return NULL;
		}

		$s = '';

		$depth = 0;
		$tokens = token_get_all("<?php return $matches[1];");
		array_shift($tokens);
		foreach ($tokens as $token) {
			if (is_array($token))
				$token = $token[1];
			switch ($token) {
			case '?':
				$s .= '? (';
				$depth++;
				break;
			case ':':
				$s .= ') : (';
				break;
			case ';':
				$s .= str_repeat(')', $depth) . ';';
				$depth = 0;
				break;
			case 'n':
				$s .= '$n';
				break;
			default:
				$s .= $token;
			}
		}

		return $s;
	}
}
