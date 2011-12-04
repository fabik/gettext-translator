<?php

require_once 'Nette/loader.php';

require_once __DIR__ . '/../GettextTranslator/GettextTranslator.php';



/**
 * Test class for GettextTranslator.
 *
 * @author     Jan-Sebastian Fabik
 */
class GettextTranslatorTest extends PHPUnit_Framework_TestCase
{
	/** @var GettextTranslator */
	protected $translator;



	public function setUp()
	{
		$tmpFile = __DIR__ . '/~serialized.tmp';

		if (file_exists($tmpFile)) {
			$serialized = file_get_contents($tmpFile);
		} else {
			$translator = new GettextTranslator(__DIR__ . '/locale.cs.mo', 'cs');
			$serialized = serialize($translator);
			file_put_contents($tmpFile, $serialized);
		}

		$this->translator = unserialize($serialized);
	}



	public function testGetLocale()
	{
		$this->assertEquals('cs', $this->translator->locale);
	}



	public function testTranslate()
	{
		$this->assertEquals('Ahoj světe!', $this->translator->translate('Hello world!'));
	}



	public function testTranslateAndFormat()
	{
		$this->assertEquals('Toto je John.', $this->translator->translate('This is %s.', 'John'));
	}



	public function testTranslatePlurals()
	{
		$this->assertEquals('1 pes', $this->translator->translate('%d dog', 1));
		$this->assertEquals('2 psi', $this->translator->translate('%d dog', 2));
		$this->assertEquals('5 psů', $this->translator->translate('%d dog', 5));
	}



	public function testTranslateAndFormatPlurals()
	{
		$this->assertEquals('Uživatel John má 1 psa.', $this->translator->translate('User %2$s has got %1$d dog.', 1, 'John'));
		$this->assertEquals('Uživatel John má 2 psy.', $this->translator->translate('User %2$s has got %1$d dog.', 2, 'John'));
		$this->assertEquals('Uživatel John má 5 psů.', $this->translator->translate('User %2$s has got %1$d dog.', 5, 'John'));
	}
}
