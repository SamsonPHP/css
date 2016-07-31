<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\css\tests;

use PHPUnit\Framework\TestCase;
use samson\core\Core;
use samsonframework\resource\ResourceMap;
use samsonphp\css\CSS;
use samsonphp\resource\ResourceValidator;

// Include framework constants
require('vendor/samsonos/php_core/src/constants.php');
require('vendor/samsonos/php_core/src/Utils2.php');

class CssTest extends TestCase
{
    /** @var CSS */
    protected $css;
    /** @var array Collection of assets */
    protected $files = [];

    public function setUp()
    {
        $this->css = new CSS(
            __DIR__,
            $this->createMock(ResourceMap::class),
            $this->createMock(Core::class)
        );

        $this->css->prepare();

        ResourceValidator::$projectRoot = __DIR__ . '/';
        ResourceValidator::$webRoot = __DIR__ . '/www/';

        // Testing other deprecated functions here
        ResourceValidator::getWebRelativePath('test.jpg', __DIR__ . '/');

        $resourcePath = __DIR__ . '/test.jpg';
        file_put_contents($resourcePath, '/** TEST */');
    }

    public function testCompile()
    {
        $css = '.class { url("tests/test.jpg"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }

    public function testCompileWithGet()
    {
        $css = '.class { url("tests/test.jpg?v=1.0"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }

    public function testCompileWithHash()
    {
        $css = '.class { url("tests/test.jpg#v=1.0"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }

    public function testCompileWithDataUri()
    {
        $css = '.class { url("data/jpeg;base64,kdFSDfsdjfnskdnfksdnfksdf"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("data/jpeg;base64,kdFSDfsdjfnskdnfksdnfksdf"); }', $css);
    }

    public function testCompileWithDataHttp()
    {
        $css = '.class { url("http://google.com/fonts/calibre"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("http://google.com/fonts/calibre"); }', $css);
    }

    public function testCompileWithDataHttps()
    {
        $css = '.class { url("https://google.com/fonts/calibre"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("https://google.com/fonts/calibre"); }', $css);
    }

    public function testCompileWithResourceNotFound()
    {
        $this->setExpectedException(\samsonphp\resource\exception\ResourceNotFound::class);

        $css = '.class { url("tests/test-tset.jpg#v=1.0"); }';

        $this->css->compile('test.css', 'css', $css);

        $this->assertEquals('.class { url("/resourcer/?p=test.jpg"); }', $css);
    }
}
