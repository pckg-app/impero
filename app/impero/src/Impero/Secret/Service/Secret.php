<?php namespace Impero\Secret\Service;

use Impero\Apache\Entity\Sites;
use Impero\Secret\Entity\Secrets;

class Secret
{

    public static function getLastSecretFor(string $morph, string $poly, string $type)
    {
        return (new Secrets())->whereArr([
            'secrets.keys->>"$.morph_id"' => $morph,
            'secrets.keys->>"$.poly_id"' => $poly,
            'secrets.keys->>"$.type"' => $type,
        ])->orderBy('id DESC')->oneOrFail();
    }

}