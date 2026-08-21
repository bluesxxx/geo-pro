<?php

namespace GeoPro\AuditEngine;

interface WebPageFetcherInterface
{
    /**
     * Fetch a public web page and return its HTML.
     *
     * @throws AuditException when the URL is unsafe, the request fails, or the page is an error response.
     */
    public function fetch(string $url): string;
}
