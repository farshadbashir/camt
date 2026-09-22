<?php

declare(strict_types=1);

namespace Genkgo\Camt\Util;

use SimpleXMLElement;

final class XmlBoolean
{
    public static function parse(SimpleXMLElement $value): bool
    {
        return in_array(trim((string) $value), ['true', '1'], true);
    }
}
