<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\DownloadSample;

/**
 * @author Patrick van Bergen
 */
class DownloadSampleInformation
{
    /**  @var DownloadSample */
    protected $downloadSample;

    /** @var string */
    private $title;

    public function __construct(DownloadSample $downloadSample, string $title)
    {
        $this->downloadSample = trim($downloadSample);
        $this->title = trim($title);
    }

    /**
     * @return DownloadSample
     */
    public function getDownloadSample(): DownloadSample
    {
        return $this->downloadSample;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}