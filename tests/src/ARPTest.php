<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\metarefresh;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\metarefresh\ARP;

class ARPTest extends TestCase
{
    public function testARP(): void
    {
        $config = Configuration::loadFromArray(
            ['module.enable' => ['metarefresh' => true]],
            '[ARRAY]',
            'simplesaml'
        );
        Configuration::setPreLoadedConfig($config, 'config.php');

        $metadata = [1 => ['metadata' => ['entityid' => 'urn:test:loeki.tv', 'attributes' => ['aap','noot','mobile']]]];
        $attributemap = 'test';
        $prefix = 'beforeit';
        $suffix = 'thereafter';

        $arp = new ARP($metadata, $attributemap, $prefix, $suffix);

        $xml = $arp->getXML();
        $expectentity = 'AttributeFilterPolicy id="urn:test:loeki.tv"><PolicyRequirementRule xsi:type="basic:AttributeRequesterString" value="urn:test:loeki.tv';
        $expectattributeunmapped = '<AttributeRule attributeID="beforeitnootthereafter"><PermitValueRule xsi:type="basic:ANY" /></AttributeRule>';
        $expectattributemapped = '<AttributeRule attributeID="beforeiturn:mace:dir:attribute-def:mobilethereafter">';

        $this->assertStringContainsString($expectentity, $xml);
        $this->assertStringContainsString($expectattributeunmapped, $xml);
        $this->assertStringContainsString($expectattributemapped, $xml);
    }
}
