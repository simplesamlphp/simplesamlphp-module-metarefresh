<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\metarefresh\Controller;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\metarefresh\Controller;
use SimpleSAML\Session;
use SimpleSAML\Utils;
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
    protected $authsources;

    /** @var \SimpleSAML\Configuration */
    protected $config;

    /** @var \SimpleSAML\Configuration */
    protected $module_config;

    /** @var \SimpleSAML\Utils\Auth */
    protected $authUtils;


    /**
     * Set up for each test.
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

        $this->module_config = Configuration::loadFromArray(
            [
                'sets' => [
                    'example' => [
                        'cron' => ['hourly'],
                        'sources' => [
                            [
                                'src' => 'https://example.org/simplesaml/module.php/aggregator/?id=kalmarcentral&set=saml2&exclude=norway'
                            ]
                        ],
                        'outputFormat' => 'flatfile',
                        'outputDir' => sys_get_temp_dir()
                    ]
                ]
            ],
            '[ARRAY]',
            'simplesaml'
        );

        $this->authsources = Configuration::loadFromArray(
            [
                'admin' => ['core:AdminPassword'],
            ],
            '[ARRAY]',
            'simplesaml'
        );

        $this->authUtils = new class () extends Utils\Auth {
            public function requireAdmin(): void
            {
                // stub
            }
        };

        Configuration::setPreLoadedConfig($this->config, 'config.php');
    }


    /**
     */
    public function testMetaRefresh()
    {
        $_SERVER['REQUEST_URI'] = '/module.php/metarefresh/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

        Configuration::setPreLoadedConfig($this->authsources, 'authsources.php');
        $request = Request::create(
            '/',
            'GET'
        );
        $session = Session::getSessionFromRequest();

        $c = new Controller\MetaRefresh($this->config, $session);
        $c->setAuthUtils($this->authUtils);
        $c->setModuleConfig($this->module_config);

        /** @var \SimpleSAML\XHTML\Template $response */
        $response = $c->main($request);

        $this->assertTrue($response->isSuccessful());
    }
}
