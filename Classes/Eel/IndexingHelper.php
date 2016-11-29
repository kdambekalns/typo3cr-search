<?php
namespace Neos\ContentRepository\Search\Eel;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Search\Exception\IndexingException;

/**
 * IndexingHelper
 */
class IndexingHelper implements ProtectedContextAwareInterface
{
    /**
     * Build all path prefixes. From an input such as:
     *
     *   /foo/bar/baz
     *
     * it emits an array with:
     *
     *   /
     *   /foo
     *   /foo/bar
     *   /foo/bar/baz
     *
     * This method works both with absolute and relative paths. If a relative path is given,
     * the returned array will lack the first element and the leading slashes, obviously.
     *
     * @param string $path
     * @return array<string>
     */
    public function buildAllPathPrefixes($path)
    {
        if (strlen($path) === 0) {
            return [];
        } elseif ($path === '/') {
            return ['/'];
        }

        $currentPath = '';
        $pathPrefixes = [];
        if ($path{0} === '/') {
            $currentPath = '/';
            $pathPrefixes[] = $currentPath;
        }
        $path = ltrim($path, '/');

        foreach (explode('/', $path) as $pathPart) {
            $currentPath .= $pathPart . '/';
            $pathPrefixes[] = rtrim($currentPath, '/');
        }

        return $pathPrefixes;
    }

    /**
     * Returns an array of node type names including the passed $nodeType and all its supertypes, recursively
     *
     * @param NodeType $nodeType
     * @return array<String>
     */
    public function extractNodeTypeNamesAndSupertypes(NodeType $nodeType)
    {
        $nodeTypeNames = [];
        $this->extractNodeTypeNamesAndSupertypesInternal($nodeType, $nodeTypeNames);
        return array_values($nodeTypeNames);
    }

    /**
     * Recursive function for fetching all node type names
     *
     * @param NodeType $nodeType
     * @param array $nodeTypeNames
     * @return void
     */
    protected function extractNodeTypeNamesAndSupertypesInternal(NodeType $nodeType, array &$nodeTypeNames)
    {
        $nodeTypeNames[$nodeType->getName()] = $nodeType->getName();
        foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
            $this->extractNodeTypeNamesAndSupertypesInternal($superType, $nodeTypeNames);
        }
    }

    /**
     * Convert an array of nodes to an array of node identifiers
     *
     * @param array<NodeInterface> $nodes
     * @return array
     */
    public function convertArrayOfNodesToArrayOfNodeIdentifiers($nodes)
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeIdentifiers = [];
        foreach ($nodes as $node) {
            $nodeIdentifiers[] = $node->getIdentifier();
        }

        return $nodeIdentifiers;
    }

    /**
     * Convert an array of nodes to an array of node property
     *
     * @param array<NodeInterface> $nodes
     * @param string $propertyName
     * @return array
     */
    public function convertArrayOfNodesToArrayOfNodeProperty($nodes, $propertyName)
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeProperties = [];
        foreach ($nodes as $node) {
            $nodeProperties[] = $node->getProperty($propertyName);
        }

        return $nodeProperties;
    }

    /**
     *
     * @param $string
     * @return array
     */
    public function extractHtmlTags($string)
    {
        // prevents concatenated words when stripping tags afterwards
        $string = str_replace(['<', '>'], [' <', '> '], $string);
        // strip all tags except h1-6
        $string = strip_tags($string, '<h1><h2><h3><h4><h5><h6>');

        $parts = [
            'text' => ''
        ];
        while (strlen($string) > 0) {
            $matches = [];
            if (preg_match('/<(h1|h2|h3|h4|h5|h6)[^>]*>.*?<\/\1>/ui', $string, $matches, PREG_OFFSET_CAPTURE)) {
                $fullMatch = $matches[0][0];
                $startOfMatch = $matches[0][1];
                $tagName = $matches[1][0];

                if ($startOfMatch > 0) {
                    $parts['text'] .= substr($string, 0, $startOfMatch);
                    $string = substr($string, $startOfMatch);
                }
                if (!isset($parts[$tagName])) {
                    $parts[$tagName] = '';
                }

                $parts[$tagName] .= ' ' . $fullMatch;
                $string = substr($string, strlen($fullMatch));
            } else {
                // no h* found anymore in the remaining string
                $parts['text'] .= $string;
                break;
            }
        }

        foreach ($parts as &$part) {
            $part = preg_replace('/\s+/u', ' ', strip_tags($part));
        }

        return $parts;
    }

    /**
     *
     *
     * @param $bucketName
     * @param $string
     * @return array
     */
    public function extractInto($bucketName, $string)
    {
        return [
            $bucketName => $string
        ];
    }

    /**
     * Index an asset list or a single asset (by base64-encoding-it);
     * in the same manner as expected by the ElasticSearch "attachment"
     * core plugin.
     *
     * @param $value
     * @return array|null|string
     * @throws IndexingException
     */
    public function indexAsset($value)
    {
        if ($value === null) {
            return null;
        } elseif (is_array($value)) {
            $result = [];
            foreach ($value as $element) {
                $result[] = $this->indexAsset($element);
            }
            return $result;
        } elseif ($value instanceof AssetInterface) {
            $stream = $value->getResource()->getStream();
            stream_filter_append($stream, 'convert.base64-encode');
            $result = stream_get_contents($stream);
            return $result;
        } else {
            throw new IndexingException('Value of type ' . gettype($value) . ' - ' . get_class($value) . ' could not be converted to asset binary.', 1437555909);
        }
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}