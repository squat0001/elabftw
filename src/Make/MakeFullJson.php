<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Make;

use Elabftw\Models\AbstractEntity;
use Override;

/**
 * Make a full JSON export, including all information from one or several entities
 * 
 * JSON EXPORT COMPONENT for timestamp assembly:
 * This class creates the JSON data file that will be packaged alongside the ASN1 
 * timestamp token in timestamp export archives. It provides complete entity data
 * using readOneFull() rather than the limited readOne() method.
 */
final class MakeFullJson extends MakeJson
{
    #[Override]
    protected function getEntityData(AbstractEntity $entity): array
    {
        return $entity->readOneFull();
    }
}
