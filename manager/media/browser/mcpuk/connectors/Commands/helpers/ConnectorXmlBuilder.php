<?php

class ConnectorXmlBuilder
{
    /** @var DOMDocument */
    private $document;

    /** @var DOMElement */
    private $root;

    /** @var string */
    private $doctype;

    public function __construct($command, $resourceType, array $options = [])
    {
        $this->document = new DOMDocument('1.0', 'utf-8');
        $this->document->formatOutput = true;
        $this->doctype = isset($options['doctype']) ? (string)$options['doctype'] : '';

        $this->root = $this->document->createElement('Connector');
        $this->root->setAttribute('command', (string)$command);
        $this->root->setAttribute('resourceType', (string)$resourceType);
        $this->document->appendChild($this->root);
    }

    public function setCurrentFolder($path, $url)
    {
        $this->addChild('CurrentFolder', [
            'path' => (string)$path,
            'url' => (string)$url,
        ]);

        return $this;
    }

    public function addChild($name, array $attributes = [], $text = null, ?DOMElement $parent = null)
    {
        $parent = $parent ?: $this->root;

        $element = $this->document->createElement($name);
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }
            $element->setAttribute($key, (string)$value);
        }

        if ($text !== null && $text !== '') {
            $element->appendChild($this->document->createTextNode((string)$text));
        }

        $parent->appendChild($element);

        return $element;
    }

    public function render()
    {
        $xml = $this->document->saveXML();

        if ($this->doctype !== '') {
            $xml = preg_replace('/^<\?xml.*?\?>/u', '$0' . "\n" . $this->doctype, $xml, 1);
        }

        return $xml;
    }
}
