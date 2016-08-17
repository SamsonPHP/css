<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 07.07.16 at 18:19
 */
namespace samsonphp\css;

use samson\core\ExternalModule;
use samsonphp\event\Event;
use samsonphp\resource\exception\ResourceNotFound;
use samsonphp\resource\ResourceValidator;
use samsonphp\resource\Router;
use samsonphp\resource\ResourceManager;

/**
 * CSS assets handling class
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 * @package samsonphp\resource
 * TODO: Remove ResourceValidator as it is unnecessary
 */
class CSS extends ExternalModule
{
    /** @var string Module identifer */
    protected $id = 'resource_css';

    /** Pattern for matching CSS url */
    const P_URL = '/url\s*\(\s*(\'|\")?([^\)\s\'\"]+)(\'|\")?\s*\)/i';

    /** Event for firing before handling CSS resource */
    const E_BEFORE_HANDLER = 'samsonphp.css.before_handle';

    /** Event for firing after handling CSS resource */
    const E_AFTER_HANDLER = 'samsonphp.css.after_handle';

    /** @var string Path to current resource file */
    protected $currentResource;

    /** Module preparation stage handler */
    public function prepare(array $params = [])
    {
        // Subscribe for CSS handling
        Event::subscribe(Router::E_RESOURCE_COMPILE, [$this, 'compile']);
        
        Event::subscribe(Compressor::E_RESOURCE_COMPRESS, [$this, 'deCompile']);

        return parent::prepare($params);
    }

    /**
     * LESS resource compiler.
     *
     * @param string $resource  Resource full path
     * @param string $extension Resource extension
     * @param string $content   Compiled output resource content
     */
    public function compile($resource, $extension, &$content)
    {
        if (in_array($extension, [ResourceManager::T_CSS, ResourceManager::T_LESS, ResourceManager::T_SASS, ResourceManager::T_SCSS])) {
            $this->currentResource = $resource;

            // Fire event
            Event::fire(self::E_BEFORE_HANDLER, [&$content, $resource]);

            // Rewrite Urls
            $content = preg_replace_callback(self::P_URL, [$this, 'rewriteUrls'], $content);

            // Fire event
            Event::fire(self::E_AFTER_HANDLER, [&$content, $resource]);
        }
    }
    
    public function deCompile($extension, &$content)
    {
        if (in_array($extension, [ResourceManager::T_CSS, ResourceManager::T_LESS, ResourceManager::T_SASS, ResourceManager::T_SCSS])) {
            if (preg_match_all('/url\s*\(\s*(\'|\")*(?<url>[^\'\"\)]+)\s*(\'|\")*\)/i', $content, $matches)) {
                if (isset($matches['url'])) {
                    foreach ($matches['url'] as $url) {
                        if (preg_match('/' . STATIC_RESOURCE_HANDLER . '\/\?p=(((\/src\/|\/vendor\/samson[^\/]+\/)(?<module>[^\/]+)(?<path>.+))|((?<local>.+)))/ui', $url, $matches)) {
                            if (array_key_exists('local', $matches)) {
                                $module = 'local';
                                $path = $matches['local'];
                            } else {
                                $module = $matches['module'];
                                $path = $matches['path'];
                            }
                            // Always remove first public path /www/
                            $path = ltrim(str_replace(__SAMSON_PUBLIC_PATH, '', $path), '/');
                            // Replace url in file
                            $content = str_replace($url, url()->base() . ($module == 'local' ? '' : $module . '/www/') . $path, $content);
                        }
                    }
                }
            }
        }
    }

    /**
     * Callback for CSS url(...) rewriting.
     *
     * @param array $matches Regular expression matches collection
     *
     * @return string Rewritten url(..) with static resource handler url
     * @throws ResourceNotFound
     */
    public function rewriteUrls($matches)
    {
        // Store static resource path
        $url = $matches[2];

        // Validate url for restricted protocols and inline images
        $validation  = array_filter(['data/', 'data:', 'http:', 'https:'], function ($item) use ($url) {
            return strpos($url, $item) !== false;
        });

        // Ignore inline resources
        if (!count($validation)) {
            // Remove possible GET, HASH parameters from resource path
            $url = $this->getOnlyUrl($this->getOnlyUrl($url, '#'), '?');

            // Try to find resource and output full error
            try {
                $path = ResourceValidator::getProjectRelativePath($url, dirname($this->currentResource));
            } catch (ResourceNotFound $e) {
                throw new ResourceNotFound('Cannot find resource "' . $url . '" in "' . $this->currentResource . '"');
            }

            // Build path to static resource handler
            return 'url("/' . STATIC_RESOURCE_HANDLER . '/?p=' . $path . '")';
        }

        return $matches[0];
    }

    /**
     * Get only path or URL before marker.
     *
     * @param string $path   Full URL with possible unneeded data
     * @param string $marker Marker for separation
     *
     * @return string Filtered asset URL
     */
    protected function getOnlyUrl($path, $marker)
    {
        // Remove possible GET parameters from resource path
        if (($getStart = strpos($path, $marker)) !== false) {
            return substr($path, 0, $getStart);
        }

        return $path;
    }
}
