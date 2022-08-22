<?php

namespace SimpleSAML\Test\Module\metarefresh;

use PHPUnit\Framework\TestCase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use SimpleSAML\Configuration;

class MetaLoaderTest extends TestCase
{
    /** \SimpleSAML\Module\metarefresh\MetaLoader */
    private $metaloader;

    /** @var \SimpleSAML\Configuration */
    private $config;

    /** @var string */
    private $tmpdir;

    /** @var array */
    private $source = [
        'outputFormat' => 'flatfile',
        'conditionalGET' => false,
        'regex-template' => [
            "#^https://idp\.example\.com/idp/shibboleth$#" => [
            'tags' => [ 'my-tag' ],
            ],
        ],
    ];

    /** @var array */
    private $expected = [
        'entityid' => 'https://idp.example.com/idp/shibboleth',
        'description' => ['en' => 'OrganizationName',],
        'OrganizationName' => ['en' => 'OrganizationName',],
        'name' => ['en' => 'DisplayName',],
        'OrganizationDisplayName' => ['en' => 'OrganizationDisplayName',],
        'url' => ['en' => 'https://example.com',],
        'OrganizationURL' => ['en' => 'https://example.com',],
        'contacts' => [['contactType' => 'technical', 'emailAddress' => ['technical.contact@example.com',],],],
        'metadata-set' => 'saml20-idp-remote',
        'SingleSignOnService' => [
            [
                'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                'Location' => 'https://idp.example.com/idp/profile/SAML2/POST/SSO',
            ],
        ],
        'keys' => [
            [
                'encryption' => true,
                'signing' => true,
                'type' => 'X509Certificate',
            ],
        ],
        'scope' => ['example.com',],
        'RegistrationInfo' => [
            'registrationAuthority' => 'http://www.surfconext.nl/',
        ],
        'EntityAttributes' => [
            'urn:oasis:names:tc:SAML:attribute:assurance-certification' => [
                0 => 'https://refeds.org/sirtfi',
            ],
            'http://macedir.org/entity-category-support' => [
                0 => 'http://refeds.org/category/research-and-scholarship',
            ],
        ],
        'UIInfo' => [
            'DisplayName' => ['en' => 'DisplayName',],
            'Description' => ['en' => 'Description',],
        ],
        'tags' => ['my-tag'],
    ];

    protected function setUp(): void
    {
        $this->config = Configuration::loadFromArray(
            ['module.enable' => ['metarefresh' => true]],
            '[ARRAY]',
            'simplesaml'
        );
        Configuration::setPreLoadedConfig($this->config, 'config.php');
        $this->metaloader = new \SimpleSAML\Module\metarefresh\MetaLoader();
        /* cannot use dirname() in declaration */
        $this->source['src'] = dirname(dirname(__FILE__)) . '/testmetadata.xml';
    }

    protected function tearDown(): void
    {
        if ($this->tmpdir && is_dir($this->tmpdir)) {
            foreach (array_diff(scandir($this->tmpdir), array('.','..')) as $file) {
                unlink($this->tmpdir . '/' . $file);
            }
            rmdir($this->tmpdir);
        }
    }

    public function testMetaLoader(): void
    {
        $this->metaloader->loadSource($this->source);
        $this->metaloader->dumpMetadataStdOut();

        /* match a line from the cert before we attempt to parse */
        $this->expectOutputRegex('/UTEbMBkGA1UECgwSRXhhbXBsZSBVbml2ZXJzaXR5MRgwFgYDVQQDDA9pZHAuZXhh/');

        $output = $this->getActualOutput();
        try {
            eval($output);
        } catch (\Exception $e) {
            $this->fail('Metarefresh does not produce syntactially valid code');
        }
        $this->assertArrayHasKey('https://idp.example.com/idp/shibboleth', $metadata);

        $this->assertTrue(
            empty(array_diff_key($this->expected, $metadata['https://idp.example.com/idp/shibboleth']))
        );
    }

    public function testSignatureVerificationCertificatePass(): void
    {
        $this->metaloader->loadSource(
            array_merge($this->source, ['certificates' => [dirname(dirname(__FILE__)) . '/mdx.pem']])
        );
        $this->metaloader->dumpMetadataStdOut();
        $this->expectOutputRegex('/UTEbMBkGA1UECgwSRXhhbXBsZSBVbml2ZXJzaXR5MRgwFgYDVQQDDA9pZHAuZXhh/');
    }

    public function testWriteMetadataFiles(): void
    {
        $this->tmpdir = tempnam(sys_get_temp_dir(), 'SSP:tests:metarefresh:');
        @unlink($this->tmpdir); /* work around post 4.0.3 behaviour */

        $this->metaloader->loadSource($this->source);
        $this->metaloader->writeMetadataFiles($this->tmpdir);
        $this->assertFileExists($this->tmpdir . '/saml20-idp-remote.php');

        @include_once($this->tmpdir . '/saml20-idp-remote.php');
        $this->assertArrayHasKey('https://idp.example.com/idp/shibboleth', $metadata);
        $this->assertTrue(
            empty(array_diff_key($this->expected, $metadata['https://idp.example.com/idp/shibboleth']))
        );
    }

    /**
     * Tests that setting an explicit expiry time will be added to the resulting
     * metadata when the original metadata lacks one.
     */
    public function testMetaLoaderSetExpiryWhenNotPresent(): void
    {
        $metaloader = new \SimpleSAML\Module\metarefresh\MetaLoader(1000);

        $metaloader->loadSource($this->source);
        $metaloader->dumpMetadataStdOut();

        $output = $this->getActualOutput();
        try {
            eval($output);
        } catch (\Exception $e) {
            $this->fail('Metarefresh does not produce syntactially valid code');
        }
        $this->assertArrayHasKey('https://idp.example.com/idp/shibboleth', $metadata);
        $this->assertArrayHasKey('expire', $metadata['https://idp.example.com/idp/shibboleth']);
        $this->assertEquals(1000, $metadata['https://idp.example.com/idp/shibboleth']['expire']);
    }

    /*
     * Test two matching EntityAttributes (R&S + Sirtfi)
     */
    public function testAttributewhitelist1(): void
    {
        $this->source['attributewhitelist'] = [
            [
                '#EntityAttributes#' => [
                    '#urn:oasis:names:tc:SAML:attribute:assurance-certification#'
                    => ['#https://refeds.org/sirtfi#'],
                    '#http://macedir.org/entity-category-support#'
                    => ['#http://refeds.org/category/research-and-scholarship#'],
                ],
            ],
        ];
        $this->metaloader->loadSource($this->source);
        $this->metaloader->dumpMetadataStdOut();
        /* match a line from the cert before we attempt to parse */
        $this->expectOutputRegex('/UTEbMBkGA1UECgwSRXhhbXBsZSBVbml2ZXJzaXR5MRgwFgYDVQQDDA9pZHAuZXhh/');

        $output = $this->getActualOutput();
        try {
            eval($output);
        } catch (\Exception $e) {
            $this->fail('Metarefresh does not produce syntactially valid code');
        }
        /* Check we matched the IdP */
        $this->assertArrayHasKey('https://idp.example.com/idp/shibboleth', $metadata);

        $this->assertTrue(
            empty(array_diff_key($this->expected, $metadata['https://idp.example.com/idp/shibboleth']))
        );
    }

    /*
     * Test non-matching of the whitelist: result should be empty set
     */
    public function testAttributewhitelist2(): void
    {
        $this->source['attributewhitelist'] = [
            [
                '#EntityAttributes#' => [
                    '#urn:oasis:names:tc:SAML:attribute:assurance-certification#'
                    => ['#https://refeds.org/sirtfi#'],
                    '#http://macedir.org/entity-category-support#'
                    => ['#http://clarin.eu/category/clarin-member#'],
                ],
            ],
        ];
        $this->metaloader->loadSource($this->source);
        $this->metaloader->dumpMetadataStdOut();

        /* Expected output is empty */
        $output = $this->getActualOutput();
        $this->assertEmpty($output);
    }

    /*
     * Test non-matching of first entry, but matching of second, using both
     * RegistrationInfo and EntityAttributes
     */
    public function testAttributewhitelist3(): void
    {
        $this->source['attributewhitelist'] = [
            [
                '#EntityAttributes#' => [
                    '#urn:oasis:names:tc:SAML:attribute:assurance-certification#'
                    => ['#https://refeds.org/sirtfi#'],
                    '#http://macedir.org/entity-category-support#'
                    => ['#http://clarin.eu/category/clarin-member#'],
                ],
            ],
            [
                '#RegistrationInfo#' => [
                    '#registrationAuthority#'
                    => '#http://www.surfconext.nl/#',
                ],
                '#EntityAttributes#' => [
                    '#urn:oasis:names:tc:SAML:attribute:assurance-certification#'
                    => ['#https://refeds.org/sirtfi#'],
                ],
            ],
        ];
        $this->metaloader->loadSource($this->source);
        $this->metaloader->dumpMetadataStdOut();
        /* match a line from the cert before we attempt to parse */
        $this->expectOutputRegex('/UTEbMBkGA1UECgwSRXhhbXBsZSBVbml2ZXJzaXR5MRgwFgYDVQQDDA9pZHAuZXhh/');

        $output = $this->getActualOutput();
        try {
            eval($output);
        } catch (\Exception $e) {
            $this->fail('Metarefresh does not produce syntactially valid code');
        }
        /* Check we matched the IdP */
        $this->assertArrayHasKey('https://idp.example.com/idp/shibboleth', $metadata);

        $this->assertTrue(
            empty(array_diff_key($this->expected, $metadata['https://idp.example.com/idp/shibboleth']))
        );
    }
}
