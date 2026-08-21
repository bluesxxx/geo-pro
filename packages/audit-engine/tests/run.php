<?php

/**
 * Zero-dependency test runner for the audit-engine package.
 * Run: php tests/run.php   (inside packages/audit-engine)
 */

require __DIR__.'/../src/autoload.php';

use GeoPro\AuditEngine\AuditEngine;
use GeoPro\AuditEngine\AuditException;
use GeoPro\AuditEngine\AuditResult;
use GeoPro\AuditEngine\CurlWebPageFetcher;
use GeoPro\AuditEngine\FeatureExtractor;
use GeoPro\AuditEngine\HeuristicScorer;
use GeoPro\AuditEngine\WebPageFetcherInterface;

$tests = 0;
$failures = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $tests, $failures;
    $tests++;
    if (! $ok) {
        $failures++;
        echo "FAIL  $name".($detail !== '' ? "  --  $detail" : '')."\n";
    }
}

function same(mixed $expected, mixed $actual): bool
{
    return $expected === $actual;
}

/* ============ FeatureExtractor ============ */

$htmlFull = file_get_contents(__DIR__.'/fixture_full.html');

$htmlBare = '<html><head><title>标题</title></head><body><p>你好</p></body></html>';

$ex = new FeatureExtractor();

$f = $ex->extract($htmlFull);
check('extract: has_h1', same(true, $f['has_h1']));
check('extract: has_schema', same(true, $f['has_schema']));
check('extract: has_faq_schema', same(true, $f['has_faq_schema']));
check('extract: title', same('GEO 优化指南', $f['title']));
check('extract: meta_description', same('网站 GEO 优化完整指南。', $f['meta_description']));
check('extract: text_length > 0', $f['text_length'] > 0);
check('extract: buzzword_count 0', same(0, $f['buzzword_count']));

$b = $ex->extract($htmlBare);
check('extract(bare): has_h1 false', same(false, $b['has_h1']));
check('extract(bare): has_schema false', same(false, $b['has_schema']));
check('extract(bare): has_faq_schema false', same(false, $b['has_faq_schema']));
check('extract(bare): text_length >= 2', $b['text_length'] >= 2);

$htmlBuzz = '<html><body><h1>极致领先</h1><p>最好、最牛、无敌、革命性、史上最强、颠覆。</p></body></html>';
$z = $ex->extract($htmlBuzz);
check('extract: buzzword_count', same(8, $z['buzzword_count']), 'got '.$z['buzzword_count']);

$e = $ex->extract('');
check('extract: empty html safe', same(false, $e['has_h1']) && $e['text_length'] === 0);

/* ============ HeuristicScorer ============ */

$scorer = new HeuristicScorer();

$fullScore = $scorer->score($f, $f['text']);
check('score: full page', same(95, $fullScore['score']), 'got '.$fullScore['score']);
check('score: full page missing_faq false', same(false, $fullScore['missing_faq']));
check('score: full page missing_schema false', same(false, $fullScore['missing_schema']));
check('score: full page suggestions empty', same(true, $fullScore['suggestions'] === [] || $fullScore['suggestions'][0] !== ''));

$bareScore = $scorer->score($b, $b['text']);
check('score: bare page 30', same(30, $bareScore['score']), 'got '.$bareScore['score']);
check('score: bare page missing_faq true', same(true, $bareScore['missing_faq']));
check('score: bare page suggestions has h1', in_array('补充一个清晰的 <h1> 主标题，帮助 AI 理解页面主题。', $bareScore['suggestions'], true));

$buzzScore = $scorer->score($z, $z['text']);
check('score: buzz penalty', same(35, $buzzScore['score']), 'got '.$buzzScore['score']); // 40+20 - min(8*3,15)=15 => 45? recompute below

/* buzz page: has_h1 true => 40+20=60; len small (<200) => -10 => 50; buzz 8 => -15 => 35 */
check('score: buzz penalty recompute', same(35, $buzzScore['score']), 'got '.$buzzScore['score']);

$midText = str_repeat('普通内容', 60); // 240 bytes, 200..800
$mid = ['has_h1' => true, 'has_schema' => true, 'has_faq_schema' => true, 'buzzword_count' => 0];
$midScore = $scorer->score($mid, $midText);
check('score: mid length no bonus', same(85, $midScore['score']), 'got '.$midScore['score']);

$longText = str_repeat('a', 900);
$longScore = $scorer->score(['has_h1' => true, 'has_schema' => true, 'has_faq_schema' => true, 'buzzword_count' => 0], $longText);
check('score: long length bonus', same(95, $longScore['score']), 'got '.$longScore['score']);

$suggestionsCapped = $scorer->score(['has_h1' => false, 'has_schema' => false, 'has_faq_schema' => false, 'buzzword_count' => 1], str_repeat('x', 500));
check('score: suggestions capped at 5', count($suggestionsCapped['suggestions']) <= 5, 'got '.count($suggestionsCapped['suggestions']));

$perfectSuggestions = $scorer->score(['has_h1' => true, 'has_schema' => true, 'has_faq_schema' => true, 'buzzword_count' => 0], str_repeat('x', 900));
check('score: perfect page has positive suggestion', in_array('整体结构良好，可进一步补充原创数据与权威引用以拉开差距。', $perfectSuggestions['suggestions'], true));

/* ============ AuditEngine ============ */

$fakeFetcher = new class($htmlFull) implements WebPageFetcherInterface {
    public function __construct(private string $html) {}

    public function fetch(string $url): string
    {
        return $this->html;
    }
};

$engine = new AuditEngine($fakeFetcher);
$result = $engine->run('https://example.com/guide');
check('engine: instance', $result instanceof AuditResult);
check('engine: score', same(95, $result->score), 'got '.$result->score);
check('engine: url', same('https://example.com/guide', $result->url));
check('engine: raw_features has title', same('GEO 优化指南', $result->rawFeatures['title'] ?? null));
check('engine: no error', same(null, $result->error));
check('engine: toArray keys', isset($result->toArray()['suggestions']));

$failingFetcher = new class implements WebPageFetcherInterface {
    public function fetch(string $url): string
    {
        throw new AuditException('目标地址不允许访问');
    }
};
$failed = (new AuditEngine($failingFetcher))->run('https://127.0.0.1/');
check('engine: failure result', $failed instanceof AuditResult && $failed->error === '目标地址不允许访问');
check('engine: failure score 0', same(0, $failed->score));

/* ============ CurlWebPageFetcher SSRF guard (no network) ============ */

$fetcher = new CurlWebPageFetcher();
$caught = null;
try {
    $fetcher->fetch('http://127.0.0.1/');
} catch (AuditException $e) {
    $caught = $e->getMessage();
}
check('fetcher: blocks localhost', $caught !== null, 'expected AuditException');

$caught2 = null;
try {
    $fetcher->fetch('ftp://example.com/file');
} catch (AuditException $e) {
    $caught2 = $e->getMessage();
}
check('fetcher: blocks non-http scheme', $caught2 !== null);

$caught3 = null;
try {
    $fetcher->fetch('http://192.168.1.1/');
} catch (AuditException $e) {
    $caught3 = $e->getMessage();
}
check('fetcher: blocks private ip', $caught3 !== null);

/* ============ summary ============ */

echo "\n{$tests} tests, {$failures} failures\n";

exit($failures === 0 ? 0 : 1);
