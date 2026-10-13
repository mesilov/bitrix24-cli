<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Input\InputDefinition;

final class RussianXmlDescriptor extends XmlDescriptor
{
    public function getInputDefinitionDocument(InputDefinition $definition): \DOMDocument
    {
        $document = parent::getInputDefinitionDocument($definition);
        $domxPath = new \DOMXPath($document);
        foreach ($definition->getOptions() as $option) {
            if (!$option->isNegatable()) {
                continue;
            }

            // Update the human annotation in the native DOM, retaining its schema.
            foreach ($domxPath->query('//option') as $node) {
                if ($node instanceof \DOMElement && $node->getAttribute('name') === '--no-' . $option->getName()) {
                    $node->getElementsByTagName('description')->item(0)->textContent = 'Отключить опцию "--' . $option->getName() . '"';
                }
            }
        }

        return $document;
    }
}
