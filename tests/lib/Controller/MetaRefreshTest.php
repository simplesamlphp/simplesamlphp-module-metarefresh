<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\metarefresh\Controller;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\metarefresh\Controller;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Set of tests for the controllers in the "metarefresh" module.
 *
 * @package SimpleSAML\Test
 */
class MetaRefreshTest extends TestCase
{
    /** @var \SimpleSAML\Configuration */
    protected $config;


    /**
     * Set up for each test.
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Configuration::loadFromArray(
            [
                'baseurlpath' => 'https://example.org/simplesaml',
                'module.enable' => ['metarefresh' => true],
            ],
            '[ARRAY]',
            'simplesaml'
        );

        Configuration::setPreLoadedConfig($this->config, 'config.php');
    }


    /**
     * @return void
     */
    public function testMetaRefresh()
    {
        $request = Request::create(
            '/',
            'GET'
        );
        $session = Session::getSessionFromRequest();

        $c = new Controller\MetaRefresh($this->config, $session);

        /** @var \SimpleSAML\XHTML\Template $response */
        $response = $c->main($request);

        $this->assertInstanceOf(Template::class, $response);
        $this->assertTrue($response->isSuccessful());
    }
}

