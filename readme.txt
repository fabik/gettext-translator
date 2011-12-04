Gettext Translator
==================

This is an add-on for the Nette Framework.


Installation
------------

This library does not require any PHP extension to be installed. It can read and
parse the binary `.mo` files on its own. This library uses several classes and
interfaces from the Nette Framework. You have to load them manually or using
auto-loading.

This library does not use the native PHP extension, so loading of big files can
be very slow. It is recommended to cache the whole object.


Usage
-----

On PHP 5.2.0 you have to remove the `use ...;` statement at the beginning of
the file.

This is how to create a new translator instance:

    $translator = new GettextTranslator('locale.mo');

You can use the following code to get the translations on-the-fly:

    $translator = new GettextTranslator('locale.cs.mo', 'cs'); // the second parameter is optional
    echo $translator->translate("Dog"); // Pes
    echo $translator->translate("Dog", 2); // Psi
    echo $translator->translate("There is %d unread comment in thread %s.", $number, 'Changelog');

    // get locale (optional)
    echo $translator->locale; // cs

Localization of Nette forms:

    $translator = new GettextTranslator('form.cs.mo', 'cs');
    $form = new Form;
    $form->setTranslator($translator);

Localization of Nette Latte templates:

    $template->setTranslator(new GettextTranslator('template.cs.mo'));

    {_'Hello! Welcome to our page!'}
    {_'There is %d unread comment in thread %s.', $number, 'Changelog'}
    {_'In thread %2$s is %1$d unread comment.', $number, 'Changelog'}

This is how to store translators in Nette cache:

    function createGettextTranslator($file, $locale = NULL)
    {
        return new GettextTranslator($file, $locale);
    }

    $cache = new Cache($storage);
    $translator = $cache->call('createGettextTranslator', 'locale.cs.mo', 'cs');


Plural Forms
------------

The translator uses English plural forms by default.

    nplurals=2; plural=n!=1;

You can specify your own in Plural-Forms header of your translation file. For
instance, this can be used for the Czech language:

    nplurals=3; plural=n==1 ? 0 : n>=2 && n<=4 ? 1 : 2;


Minimum requirements
--------------------

- PHP 5.2.0 or 5.3.0 (http://php.net)
- Nette Framework 2.0 (http://nette.org)


Documentation, Examples, Sandbox, Tools
---------------------------------------

homepage: http://addons.nette.org/gettext-translator
repository: http://github.com/fabik/gettext-translator
