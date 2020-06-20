<?php

declare(strict_types=1);

namespace SimpleSAML\Module\metarefresh\Controller;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module\metarefresh\MetaLoader;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the metarefresh module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\metarefresh
 */

class MetaRefresh
{
    /** @var \SimpleSAML\Configuration */
    protected $config;

    /** @var \SimpleSAML\Session */
    protected $session;

    /** @var \SimpleSAML\Configuration */
    protected $module_config;

    /**
     * @var \SimpleSAML\Utils\Auth|string
     * @psalm-var \SimpleSAML\Utils\Auth|class-string
     */
    protected $authUtils = Utils\Auth::class;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration and auth source configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration              $config The configuration to use by the controllers.
     * @param \SimpleSAML\Session                    $session The session to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->module_config = Configuration::getOptionalConfig('module_metarefresh.php');
    }


    /**
     * Inject the \SimpleSAML\Utils\Auth dependency.
     *
     * @param \SimpleSAML\Utils\Auth $authUtils
     */
    public function setAuthUtils(Utils\Auth $authUtils): void
    {
        $this->authUtils = $authUtils;
    }


    /**
     * @return \SimpleSAML\XHTML\Template
     */
    public function main(): Template
    {
        $this->authUtils::requireAdmin();

        Logger::setCaptureLog(true);
        $sets = $this->module_config->getArray('sets', []);

        foreach ($sets as $setkey => $set) {
            $set = Configuration::loadFromArray($set);

            Logger::info('[metarefresh]: Executing set [' . $setkey . ']');

            try {
                $expireAfter = $set->getInteger('expireAfter', null);
                if ($expireAfter !== null) {
                    $expire = time() + $expireAfter;
                } else {
                    $expire = null;
                }
                $metaloader = new MetaLoader($expire);

                // Get global black/whitelists
                $blacklist = $this->module_config->getArray('blacklist', []);
                $whitelist = $this->module_config->getArray('whitelist', []);

                // get global type filters
                $available_types = [
                    'saml20-idp-remote',
                    'saml20-sp-remote',
                    'attributeauthority-remote'
                ];
                $set_types = $set->getArrayize('types', $available_types);

                foreach ($set->getArray('sources') as $source) {
                    // filter metadata by type of entity
                    if (isset($source['types'])) {
                        $metaloader->setTypes($source['types']);
                    } else {
                        $metaloader->setTypes($set_types);
                    }

                    // Merge global and src specific blacklists
                    if (isset($source['blacklist'])) {
                        $source['blacklist'] = array_unique(array_merge($source['blacklist'], $blacklist));
                    } else {
                        $source['blacklist'] = $blacklist;
                    }

                    // Merge global and src specific whitelists
                    if (isset($source['whitelist'])) {
                        $source['whitelist'] = array_unique(array_merge($source['whitelist'], $whitelist));
                    } else {
                        $source['whitelist'] = $whitelist;
                    }

                    Logger::debug(
                        '[metarefresh]: In set [' . $setkey . '] loading source [' . $source['src'] . ']'
                    );
                    $metaloader->loadSource($source);
                }

                $outputDir = $set->getString('outputDir');
                $outputDir = Utils\System::resolvePath($outputDir);

                $outputFormat = $set->getValueValidate('outputFormat', ['flatfile', 'serialize'], 'flatfile');
                switch ($outputFormat) {
                    case 'flatfile':
                        $metaloader->writeMetadataFiles($outputDir);
                        break;
                    case 'serialize':
                        $metaloader->writeMetadataSerialize($outputDir);
                        break;
                }
            } catch (Exception $e) {
                $e = Error\Exception::fromException($e);
                $e->logWarning();
            }
        }

        $logentries = Logger::getCapturedLog();

        $t = new Template($this->config, 'metarefresh:fetch.twig');
        $t->data['logentries'] = $logentries;
        return $t;
    }
}
