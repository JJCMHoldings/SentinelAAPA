<?php

namespace ChurchCRM\Plugins\Sentinel\Model\Base\Collection;

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Formatter\ObjectFormatter;

/**
 * Custom collection for SentinelRecord.
 *
 * @extends \Propel\Runtime\Collection\ObjectCollection<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>
 */
class SentinelRecordCollection extends ObjectCollection
{
    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setModel('\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord');
        $this->setFormatter(new ObjectFormatter());
    }

}
