<?php

namespace SimpleSAML\Test\Module\metarefresh;

use PHPUnit\Framework\TestCase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use SimpleSAML\Configuration;

class MetaLoaderTest extends TestCase
{
    private $metaloader;
    private $config;
    private $tmpdir;
    private $source = [
        'outputFormat' => 'flatfile',
        'conditionalGET' => false,
    ];
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
        'UIInfo' => [
            'DisplayName' => ['en' => 'DisplayName',],
            'Description' => ['en' => 'Description',],
        ],
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
}
