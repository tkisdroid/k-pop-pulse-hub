"""Read-only HTTP SEO checks; optionally crawl every current sitemap URL."""
import argparse, concurrent.futures, html, json, re, urllib.error, urllib.request
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
from urllib.parse import urlparse

class Page(HTMLParser):
    def __init__(self):
        super().__init__(); self.meta = {}; self.canonicals = []; self.h1 = 0; self.title = ''; self.in_title = False; self.in_ld = False; self.ld = []; self.chunk = ''
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'meta': self.meta.setdefault(a.get('name') or a.get('property'), []).append(a.get('content', ''))
        if tag == 'link' and a.get('rel') == 'canonical': self.canonicals.append(a.get('href'))
        if tag == 'h1': self.h1 += 1
        if tag == 'title': self.in_title = True
        if tag == 'script' and a.get('type') == 'application/ld+json': self.in_ld = True; self.chunk = ''
    def handle_endtag(self, tag):
        if tag == 'title': self.in_title = False
        if tag == 'script' and self.in_ld: self.ld.append(json.loads(self.chunk)); self.in_ld = False
    def handle_data(self, data):
        if self.in_title: self.title += data
        if self.in_ld: self.chunk += data

def fetch(url):
    request = urllib.request.Request(url, headers={'User-Agent': 'KpopBlog-SEO-Verification/1.0'})
    try:
        with urllib.request.urlopen(request, timeout=30) as r: return r.status, r.geturl(), r.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.geturl(), e.read().decode()

def main():
    parser = argparse.ArgumentParser(); parser.add_argument('base'); parser.add_argument('--all', action='store_true'); parser.add_argument('--output'); args = parser.parse_args()
    base = args.base.rstrip('/'); status, _, xml = fetch(base + '/sitemap.xml'); assert status == 200
    root = ET.fromstring(xml); assert root.tag.endswith('urlset'), 'Expected canonical URL sitemap'
    urls = [e.text for e in root.iter() if e.tag.endswith('}loc')]
    assert len(urls) == len(set(urls)) and len(urls) <= 50000
    assert all(urlparse(u).netloc == urlparse(base).netloc and not urlparse(u).query for u in urls)
    assert not any(urlparse(u).path.startswith(('/newsletter','/profile','/admin','/search','/login')) for u in urls)
    assert len(xml.encode()) < 50 * 1024 * 1024
    _, _, robots = fetch(base + '/robots.txt'); assert 'Sitemap: ' + base + '/sitemap.xml' in robots
    status, _, news = fetch(base + '/news-sitemap.xml'); assert status == 200; ET.fromstring(news)
    selection = urls if args.all else urls[:18] + [next(u for u in urls if prefix in urlparse(u).path) for prefix in ['/news/','/artist/','/member/','/watch/','/thread/','/polls/'] if any(prefix in urlparse(u).path for u in urls)]
    failures = []
    def verify(url):
        try:
            status, final, body = fetch(url); page = Page(); page.feed(body)
            assert status == 200, f'HTTP {status}'
            assert page.canonicals == [url], f'canonical {page.canonicals}'
            assert page.title.strip() and len(page.meta.get('description',[])) == 1
            assert page.h1 == 1, f'H1 count {page.h1}'
            assert not any('noindex' in v for v in page.meta.get('robots',[]))
            assert len(page.ld) == 1 and page.ld[0].get('@context') == 'https://schema.org'
            assert len(page.meta.get('og:image',[])) == 1 and page.meta['og:image'][0].startswith(('https://','http://localhost'))
            assert not re.search(r'(?:Fatal error|Warning|Deprecated):',body)
            return None
        except Exception as error: return {'url':url,'error':str(error)}
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: failures = [e for e in pool.map(verify,selection) if e]
    for path in ['/no-such-seo-page','/news/no-such-seo-page','/member/no-such-seo-page','/watch/999999999']:
        status, _, body = fetch(base + path)
        if status != 404 or 'noindex' not in body: failures.append({'url':base+path,'error':'soft 404'})
    for path in ['/login','/search','/newsletter','/profile/me']:
        status, _, body = fetch(base+path); page=Page(); page.feed(body)
        if status != 200 or not any('noindex' in v for v in page.meta.get('robots',[])): failures.append({'url':base+path,'error':'utility indexing'})
    result={'base':base,'sitemap_urls':len(urls),'checked_pages':len(selection),'failures':failures}
    if args.output: open(args.output,'w').write(json.dumps(result,indent=2)+'\n')
    print(json.dumps(result,indent=2)); raise SystemExit(1 if failures else 0)
if __name__ == '__main__': main()
