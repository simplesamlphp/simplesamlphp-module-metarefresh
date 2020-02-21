<?php

use Webmozart\Assert\Assert;

/**
 * Hook to add links to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 * @return void
 */
function metarefresh_hook_frontpage(array &$links)
{
    Assert::keyExists($links, 'links');

    $links['federation'][] = [
        'href' => SimpleSAML\Module::getModuleURL('metarefresh/fetch.php'),
        'text' => '{metarefresh:metarefresh:frontpage_link}',
    ];
}
