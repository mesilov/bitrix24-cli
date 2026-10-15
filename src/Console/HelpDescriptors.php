<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Helper\DescriptorHelper;

final class HelpDescriptors
{
    public static function create(): DescriptorHelper
    {
        $descriptorHelper = new DescriptorHelper();
        $descriptorHelper->register('txt', new TaskHelpDescriptor());
        $descriptorHelper->register('md', new RussianMarkdownDescriptor());
        $descriptorHelper->register('json', new RussianJsonDescriptor());
        $descriptorHelper->register('xml', new RussianXmlDescriptor());
        return $descriptorHelper;
    }
}
