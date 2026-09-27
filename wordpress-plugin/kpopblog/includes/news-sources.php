<?php
/**
 * News sources, relevance gates, on-site summaries, duplicate-story merging,
 * and the article footer that keeps readers on the site.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Built-in publisher feeds.
 *
 * gate: none        dedicated K-pop feed, keep everything
 *       kpop        K-pop outlet that also covers drama and film
 *       kpop-strong general entertainment or news feed; needs clear K-pop markers
 *       artist      general music or fashion outlet; needs a followed artist in the headline
 * gossip: skip rumor-style headlines ("goes viral", "netizens", "backlash")
 */
function kpopblog_collector_source_catalog() {
	return array(
		'soompi'        => array( 'name' => 'Soompi', 'url' => 'https://www.soompi.com/feed', 'lang' => 'en', 'gate' => 'kpop', 'default' => 1 ),
		'billboard'     => array( 'name' => 'Billboard', 'url' => 'https://www.billboard.com/t/k-pop/feed/', 'lang' => 'en', 'gate' => 'none', 'default' => 1 ),
		'koreaherald'   => array( 'name' => 'The Korea Herald', 'url' => 'https://www.koreaherald.com/rss/newsAll', 'lang' => 'en', 'gate' => 'kpop-strong', 'default' => 1 ),
		'yonhap'        => array( 'name' => 'Yonhap News Agency', 'url' => 'https://en.yna.co.kr/RSS/culture.xml', 'lang' => 'en', 'gate' => 'kpop-strong', 'default' => 1 ),
		'koreatimes'    => array( 'name' => 'The Korea Times', 'url' => 'https://www.koreatimes.co.kr/www/rss/entertainment.xml', 'lang' => 'en', 'gate' => 'kpop-strong', 'default' => 1 ),
		'kbsworld'      => array( 'name' => 'KBS World', 'url' => 'https://world.kbs.co.kr/rss/rss_news.htm?lang=e', 'lang' => 'en', 'gate' => 'kpop-strong', 'default' => 1 ),
		'nme'           => array( 'name' => 'NME', 'url' => 'https://www.nme.com/news/music/feed', 'lang' => 'en', 'gate' => 'artist', 'default' => 1 ),
		'rollingstone'  => array( 'name' => 'Rolling Stone', 'url' => 'https://www.rollingstone.com/music/music-news/feed/', 'lang' => 'en', 'gate' => 'artist', 'default' => 1 ),
		'variety'       => array( 'name' => 'Variety', 'url' => 'https://variety.com/v/music/feed/', 'lang' => 'en', 'gate' => 'artist', 'default' => 1 ),
		'teenvogue'     => array( 'name' => 'Teen Vogue', 'url' => 'https://www.teenvogue.com/feed/rss', 'lang' => 'en', 'gate' => 'artist', 'default' => 1 ),
		'hypebae'       => array( 'name' => 'Hypebae', 'url' => 'https://www.hypebae.com/feed', 'lang' => 'en', 'gate' => 'artist', 'default' => 1 ),
		'koreaboo'      => array( 'name' => 'Koreaboo', 'url' => 'https://www.koreaboo.com/feed/', 'lang' => 'en', 'gate' => 'kpop', 'gossip' => 1, 'default' => 1 ),
		'kbizoom'       => array( 'name' => 'KBIZoom', 'url' => 'https://kbizoom.com/feed/', 'lang' => 'en', 'gate' => 'kpop-strong', 'gossip' => 1, 'default' => 1 ),
		'yna-ko'        => array( 'name' => '연합뉴스', 'url' => 'https://www.yna.co.kr/rss/entertainment.xml', 'lang' => 'ko', 'gate' => 'kpop-strong', 'default' => 0 ),
		'newsis-ko'     => array( 'name' => '뉴시스', 'url' => 'https://newsis.com/RSS/entertain.xml', 'lang' => 'ko', 'gate' => 'kpop-strong', 'default' => 0 ),
		'sportsdonga-ko'=> array( 'name' => '스포츠동아', 'url' => 'https://rss.donga.com/sportsdonga/entertainment.xml', 'lang' => 'ko', 'gate' => 'kpop-strong', 'gossip' => 1, 'default' => 0 ),
		'sbs-ko'        => array( 'name' => 'SBS 연예뉴스', 'url' => 'https://news.sbs.co.kr/news/SectionRssFeed.do?sectionId=14', 'lang' => 'ko', 'gate' => 'kpop-strong', 'default' => 0 ),
		'chosun-ko'     => array( 'name' => '조선일보', 'url' => 'https://www.chosun.com/arc/outboundfeeds/rss/category/entertainments/?outputType=xml', 'lang' => 'ko', 'gate' => 'kpop-strong', 'gossip' => 1, 'default' => 0 ),
	);
}

function kpopblog_collector_default_sources() {
	return array_keys( array_filter( kpopblog_collector_source_catalog(), function ( $source ) {
		return ! empty( $source['default'] );
	} ) );
}

/** Publisher name for a URL: catalogue name when the host matches, else the bare host. */
function kpopblog_collector_publisher_from_url( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$host = preg_replace( '/^(www|en|m|rss|news|world)\./', '', $host );
	foreach ( kpopblog_collector_source_catalog() as $source ) {
		$source_host = preg_replace( '/^(www|en|m|rss|news|world)\./', '', strtolower( (string) wp_parse_url( $source['url'], PHP_URL_HOST ) ) );
		if ( $source_host === $host ) { return $source['name']; }
	}
	$known = array( 'youtube.com' => 'YouTube', 'soompi.com' => 'Soompi' );
	return isset( $known[ $host ] ) ? $known[ $host ] : $host;
}

/* ---------- relevance ---------- */

/**
 * Decide whether a feed item belongs on a K-pop site.
 *
 * @param array $source         Catalogue entry (gate, gossip).
 * @param array $title_artists  Followed artists named in the headline.
 * @param bool  $from_artist_feed Item came from a per-artist feed.
 */
function kpopblog_collector_passes_gate( array $source, $title, $summary, array $categories, array $title_artists, $from_artist_feed = false ) {
	if ( preg_match( '/^\s*(quiz|poll)\b|^\s*\[?(photos?|포토|화보)\]?\s*$/iu', $title ) ) { return false; }
	if ( ! empty( $source['gossip'] ) && preg_match( '/netizens?|goes viral|went viral|backlash|slammed|throws? shade|caught with|dating rumou?r|leaked|allegedly|controvers|affair|scandal|누리꾼|네티즌|열애설|논란|의혹|폭로/iu', $title ) ) {
		return false;
	}
	if ( $from_artist_feed || $title_artists ) { return true; }

	$gate = isset( $source['gate'] ) ? $source['gate'] : 'kpop';
	if ( 'none' === $gate ) { return true; }
	if ( 'artist' === $gate ) { return false; }

	$text   = $title . ' ' . $summary;
	$cats   = strtolower( implode( '|', $categories ) );
	$strong = preg_match( '/k-?pop|idol|girl group|boy group|comeback|music show|inkigayo|music bank|m countdown|show champion|fandom|trainee|hybe|bighit|sm entertainment|jyp|yg entertainment|starship|cube entertainment|pledis|케이팝|K-?팝|아이돌|걸그룹|보이그룹|컴백|음악방송|팬덤|연습생|하이브|빅히트|SM엔터|JYP|YG엔터|스타쉽/iu', $text );
	$music  = preg_match( '/debut|mini album|\balbum\b|\bEP\b|single|music video|\bM\/?V\b|fan ?meeting|fan-?con|world tour|concert|billboard|melon|circle chart|hanteo|choreograph|audition|데뷔|앨범|신곡|뮤직비디오|음원|차트|콘서트|월드투어|팬미팅|발매/iu', $text );
	$screen = preg_match( '/k-?drama|drama|film|movie|box office|actor|actress|series|variety show|webtoon|드라마|영화|배우|예능|박스오피스|시청률/iu', $title )
		|| false !== strpos( $cats, 'tv/film' ) || false !== strpos( $cats, 'drama' );

	if ( 'kpop-strong' === $gate ) {
		return (bool) $strong && ! ( $screen && ! $music );
	}
	// kpop: dedicated K-pop outlet that also runs drama and film stories.
	if ( false !== strpos( $cats, 'music' ) || false !== strpos( $cats, 'kpop' ) || false !== strpos( $cats, 'k-pop' ) ) {
		return ! $screen || (bool) $music;
	}
	return ( $strong || $music ) && ! $screen;
}

function kpopblog_collector_category( $title ) {
	if ( preg_match( '/\baward|\bwins?\b|\btrophy|takes? (home )?(1st|first)|daesang|\bMAMA\b|golden disc|music show win|수상|시상식|트로피|대상/iu', $title ) ) { return 'Awards'; }
	if ( preg_match( '/chart|billboard|\bNo\. ?1\b|number one|melon|circle|hanteo|oricon|spotify|streams|sales|brand reputation|record|million|차트|1위|빌보드|멜론|음원|판매량|브랜드평판/iu', $title ) ) { return 'Charts'; }
	if ( preg_match( '/comeback|teaser|pre-release|track ?list|\balbum|\bEP\b|single|\bM\/?V\b|music video|release|debut|drops?\b|컴백|앨범|발매|티저|뮤직비디오|신곡|데뷔/iu', $title ) ) { return 'Comeback'; }
	if ( preg_match( '/concert|tour|fan ?meeting|fan-?con|festival|stadium|encore|lineup|showcase|headline|콘서트|투어|팬미팅|공연|페스티벌|쇼케이스/iu', $title ) ) { return 'Tour'; }
	return 'News';
}

/* ---------- summaries ---------- */

/** Decode HTML entities until stable (feeds are often double- or triple-encoded). */
function kpopblog_collector_decode( $text ) {
	for ( $pass = 0; $pass < 3; $pass++ ) {
		$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded === $text ) { break; }
		$text = $decoded;
	}
	return $text;
}

/**
 * Plain text from feed or page HTML, one paragraph per line, with syndication
 * boilerplate, "read more" teasers, photo captions, and wire datelines removed.
 */
function kpopblog_collector_plain_text( $html ) {
	$html = preg_replace( '#<(script|style|figure|figcaption|iframe|blockquote|aside|nav)[^>]*>.*?</\1>#is', ' ', (string) $html );
	$html = preg_replace( '#<a[^>]*class="more-link"[^>]*>.*?</a>#is', '', $html );
	$html = preg_replace( '#</?(p|div|h[1-6]|li|ul|ol|section|article|header|footer|table|tr)\b[^>]*>|<br\s*/?>#i', "\n", $html );
	$text = kpopblog_collector_decode( wp_strip_all_tags( $html, false ) );
	$paragraphs = array();
	foreach ( preg_split( '/\n+/u', $text ) as $paragraph ) {
		$paragraph = trim( preg_replace( '/[ \t\x{00A0}]+/u', ' ', $paragraph ) );
		if ( '' === $paragraph ) { continue; }
		// WordPress feed trailer ("The post X appeared first on Y.") and anything after it.
		$paragraph = preg_replace( '/\s*The post .{0,300}? appeared first on .*$/u', '', $paragraph );
		$paragraph = preg_replace( '/\s*(READ MORE|Read more|Read More|RELATED|Related|ALSO READ|Also read|MORE):.*$/u', '', $paragraph );
		$paragraph = preg_replace( '/\s*(Continue reading|더보기).*$/u', '', $paragraph );
		// Wire datelines and bylines: "SEOUL, Sept. 26 (Yonhap) --", "(서울=연합뉴스) 기자 =", "[스포츠동아 기자]".
		$paragraph = preg_replace( '/^[A-Z][A-Za-z .,]{2,40}\d{1,2}\s*\((Yonhap|AFP|AP|Reuters)\)\s*--\s*/u', '', $paragraph );
		$paragraph = preg_replace( '/^[\(\[][^\)\]]{0,30}(=|기자)[^\)\]]{0,30}[\)\]]\s*([^\s=]{1,12}\s*(기자|특파원)\s*=)?\s*/u', '', $paragraph );
		$paragraph = preg_replace( '/^[^\s=]{1,12}\s*(기자|특파원)\s*=\s*/u', '', $paragraph );
		$paragraph = trim( $paragraph );
		// Photo captions and credits: "Yiren | @yiren_www/Instagram", "Photo: Getty Images".
		if ( '' === $paragraph || preg_match( '/\|\s*@|@[\w.]+\s*\/\s*(instagram|x|twitter|weverse|tiktok|youtube)|^(photo|image|credit|source)s?\s*[:|]|^\S+(\s\S+){0,4}\s*\|\s*\S+(\s\S+){0,4}$/iu', $paragraph ) ) {
			continue;
		}
		$paragraphs[] = $paragraph;
	}
	return implode( "\n", $paragraphs );
}

/** Split text into sentences (English and Korean); paragraph breaks always end a sentence. */
function kpopblog_collector_sentences( $text ) {
	$sentences = array();
	foreach ( preg_split( '/\n+/u', (string) $text ) as $paragraph ) {
		$paragraph = trim( preg_replace( '/\s+/u', ' ', $paragraph ) );
		if ( '' === $paragraph ) { continue; }
		// Missing space after a full stop ("YouTube.Yura") from joined paragraphs.
		$paragraph = preg_replace( '/([a-z가-힣][.!?])([A-Z][a-z])/u', '$1 $2', $paragraph );
		$parts = preg_split( '/(?:(?<=[.!?…])|(?<=[.!?…]["”’\')\]]))\s+(?=["“‘(\[]?[A-Z0-9가-힣])/u', $paragraph );
		// A standfirst often has no final full stop ("…for the season 52 premiere"). Close it,
		// unless it was cut off mid-phrase ("heralding the start of a").
		$tail = count( $parts ) - 1;
		if ( $tail >= 0 && ! preg_match( '/[.!?…"”’)\]]$/u', $parts[ $tail ] ) && ! preg_match( '/\b(a|an|the|of|to|and|or|for|with|in|on|at|by|from|as|that|which|who|its|their|her|his|is|are|was|were)$/i', $parts[ $tail ] ) && preg_match( '/[\p{L}\p{N}]$/u', $parts[ $tail ] ) ) {
			$parts[ $tail ] .= '.';
		}
		$start = count( $sentences );
		foreach ( array_map( 'trim', $parts ) as $part ) {
			if ( '' === $part ) { continue; }
			$last = count( $sentences ) - 1;
			// Re-join splits after abbreviations and initials ("No. 1", "Sept. 26", "J. Cole").
			if ( $last >= $start && preg_match( '/(\b(No|Nos|Mr|Mrs|Ms|Dr|St|vs|Vol|Pt|feat|ft|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|U\.S|U\.K|a\.m|p\.m)|\b[A-Z])\.$/', $sentences[ $last ] ) ) {
				$sentences[ $last ] .= ' ' . $part;
			} else {
				$sentences[] = $part;
			}
		}
	}
	return $sentences;
}

/** Sentences that only make sense on the source page, or add nothing. */
function kpopblog_collector_is_filler( $sentence ) {
	if ( preg_match( '/^(watch|listen( to)?|check (it |them )?out|see|take a look|catch)\b|\b(below|above)\b|\bhere[.!]$|stay tuned|sign up|subscribe|follow us|click|photo credit|getty images|all rights reserved|©|independently chosen|may receive (a )?commission|earn (a )?commission|affiliate|retail links|let google show|cookie|privacy policy|terms of (use|service)|enable javascript|사진=|사진 제공|무단 ?전재|재배포 금지|기사제보/iu', $sentence ) ) {
		return true;
	}
	// Shopping widgets ("$11.76 $12.99 9% off. Buy Now On Amazon.").
	if ( preg_match( '/[$€£₩]\s?\d|\d+% off|buy now|shop now|on amazon|add to cart|advertisement|sponsored/iu', $sentence ) ) {
		return true;
	}
	// Fragments such as "Related." that survive markup removal (Korean sentences are not space-delimited the same way).
	if ( ! preg_match( '/[가-힣]/u', $sentence ) && str_word_count( $sentence ) < 4 ) {
		return true;
	}
	// Short cheers such as "Get ready DIVE!" or "XLOV has kicked off a new era!".
	return kpopblog_collector_strlen( $sentence ) < 45 && preg_match( '/!["”’]?$/u', $sentence );
}

function kpopblog_collector_strlen( $text ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
}

function kpopblog_collector_sentence_key( $sentence ) {
	return strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', $sentence ) );
}

/**
 * Build an on-site summary from the publisher's own syndication text: the
 * standfirst plus the lead sentences, without filler, capped in length.
 *
 * @param string[] $texts Candidate texts in priority order (feed description, feed body, page description, page lead).
 */
function kpopblog_collector_build_summary( array $texts, $title = '', $max_chars = 520, $max_sentences = 4 ) {
	$picked    = array();
	$keys      = array();
	$length    = 0;
	$fallback  = '';
	$title_key = kpopblog_collector_sentence_key( $title );
	foreach ( $texts as $text ) {
		foreach ( kpopblog_collector_sentences( kpopblog_collector_plain_text( $text ) ) as $sentence ) {
			$key = kpopblog_collector_sentence_key( $sentence );
			if ( '' === $key || $key === $title_key || kpopblog_collector_is_filler( $sentence ) ) { continue; }
			// Same sentence seen already, or a truncated copy of one (og:description cut-offs).
			foreach ( $keys as $known ) {
				if ( 0 === strpos( $known, $key ) || 0 === strpos( $key, $known ) ) { continue 2; }
			}
			$complete = (bool) preg_match( '/[.!?…다요]["”’)\]]?$/u', $sentence ) && ! preg_match( '/(\.\.\.|…)$/u', $sentence );
			// Unbalanced quotes mean the split landed inside a quotation.
			$balanced = substr_count( $sentence, '“' ) === substr_count( $sentence, '”' );
			if ( ! $complete || ! $balanced ) {
				// Keep the first cut-off sentence only as a last resort; a later text may carry it in full.
				if ( '' === $fallback && ! $picked ) { $fallback = $sentence; }
				continue;
			}
			$sentence_length = kpopblog_collector_strlen( $sentence );
			if ( $picked && ( $length + $sentence_length > $max_chars || count( $picked ) >= $max_sentences ) ) { break 2; }
			$picked[] = $sentence;
			$keys[]   = $key;
			$length  += $sentence_length + 1;
		}
		if ( $length >= $max_chars * 0.75 ) { break; }
	}
	$summary = $picked ? implode( ' ', $picked ) : $fallback;
	if ( kpopblog_collector_strlen( $summary ) > $max_chars && function_exists( 'mb_substr' ) ) {
		$summary = rtrim( mb_substr( $summary, 0, $max_chars - 1, 'UTF-8' ) ) . '…';
	}
	return $summary;
}

/**
 * Fetch the source page once for its preview image, description, and lead
 * paragraphs.
 *
 * @return array{image:string,description:string,lead:string}
 */
function kpopblog_collector_fetch_page_meta( $url ) {
	$out = array( 'image' => '', 'description' => '', 'lead' => '' );
	$response = wp_safe_remote_get( $url, array(
		'timeout'             => 8,
		'redirection'         => 3,
		'limit_response_size' => 600 * KB_IN_BYTES,
		'user-agent'          => 'Mozilla/5.0 (compatible; KpopBlogBot/1.0; +' . home_url( '/' ) . ')',
	) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return $out; }
	$html = (string) wp_remote_retrieve_body( $response );

	$meta = function ( $names ) use ( $html ) {
		foreach ( $names as $name ) {
			$quoted = preg_quote( $name, '/' );
			if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . $quoted . '["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $match )
				|| preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . $quoted . '["\']/i', $html, $match ) ) {
				$value = trim( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' !== $value ) { return $value; }
			}
		}
		return '';
	};
	$out['image']       = esc_url_raw( $meta( array( 'og:image', 'twitter:image' ) ), array( 'https' ) );
	$out['description'] = $meta( array( 'og:description', 'description', 'twitter:description' ) );

	// Lead paragraphs from the article body, skipping navigation and legal text.
	$body = $html;
	if ( preg_match( '#<article[^>]*>(.*?)</article>#is', $html, $article ) ) { $body = $article[1]; }
	$lead = array();
	if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $body, $paragraphs ) ) {
		foreach ( $paragraphs[1] as $paragraph ) {
			$text = trim( kpopblog_decode_text_entities( $paragraph ) );
			if ( kpopblog_collector_strlen( $text ) < 60 || preg_match( '/cookie|subscribe|newsletter|sign up|all rights reserved|©|copyright|advertis|let google show|javascript|광고|저작권|무단/iu', $text ) ) { continue; }
			$lead[] = $text;
			if ( count( $lead ) >= 3 ) { break; }
		}
	}
	$out['lead'] = implode( "\n\n", $lead );
	return $out;
}

/* ---------- duplicate stories ---------- */

function kpopblog_collector_title_tokens( $title ) {
	static $stop = array( 'the', 'and', 'for', 'with', 'from', 'into', 'over', 'after', 'their', 'her', 'his', 'its', 'new', 'are', 'was', 'has', 'have', 'will', 'this', 'that', 'what', 'who', 'how', 'why', 'kpop', 'k-pop', 'watch', 'listen', 'update', 'official', 'says', 'reveals', 'announces', 'shares' );
	$title = strtolower( preg_replace( '/[^\p{L}\p{N}\s-]+/u', ' ', kpopblog_decode_text_entities( $title ) ) );
	$tokens = array();
	foreach ( preg_split( '/\s+/u', $title ) as $token ) {
		$token = trim( $token, '-' );
		if ( kpopblog_collector_strlen( $token ) < 2 || in_array( $token, $stop, true ) ) { continue; }
		$tokens[ $token ] = true;
	}
	return array_keys( $tokens );
}

function kpopblog_collector_similarity( array $a, array $b ) {
	if ( ! $a || ! $b ) { return 0.0; }
	$shared = count( array_intersect( $a, $b ) );
	return $shared / max( 1, min( count( $a ), count( $b ) ) );
}

/**
 * Recently published collector stories for duplicate checks.
 *
 * @return array<int,array{post_id:int,tokens:array,artists:array,publisher:string}>
 */
function kpopblog_collector_recent_stories( $hours = 72 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT wp_post_id, sources_json FROM {$wpdb->prefix}kb_automation_items WHERE kind = 'rss' AND first_seen_at > %s ORDER BY id DESC LIMIT 400",
		gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS )
	) );
	$stories = array();
	foreach ( (array) $rows as $row ) {
		$sources = json_decode( (string) $row->sources_json, true );
		$first = is_array( $sources ) && isset( $sources[0] ) ? $sources[0] : array();
		if ( empty( $first['title'] ) || ! get_post( (int) $row->wp_post_id ) ) { continue; }
		$stories[] = array(
			'post_id'   => (int) $row->wp_post_id,
			'tokens'    => kpopblog_collector_title_tokens( $first['title'] ),
			'artists'   => array_values( array_filter( (array) get_post_meta( (int) $row->wp_post_id, 'kb_related_artist_slugs', true ), 'strlen' ) ),
			'publisher' => isset( $first['publisher'] ) ? (string) $first['publisher'] : '',
		);
	}
	return $stories;
}

/**
 * Same story as an earlier one? Headlines must overlap strongly; a shared
 * artist lowers the bar slightly.
 */
function kpopblog_collector_find_duplicate( array $item, array $stories ) {
	$tokens = kpopblog_collector_title_tokens( $item['title'] );
	if ( count( $tokens ) < 3 ) { return 0; }
	foreach ( $stories as $story ) {
		$score = kpopblog_collector_similarity( $tokens, $story['tokens'] );
		$shared_artist = (bool) array_intersect( $item['artists'], $story['artists'] );
		if ( $score >= 0.8 || ( $shared_artist && $score >= 0.6 ) ) { return (int) $story['post_id']; }
	}
	return 0;
}

/** All sources credited on an article: [{url,title,publisher}]. */
function kpopblog_collector_post_sources( $post_id ) {
	$sources = get_post_meta( $post_id, 'kb_sources', true );
	if ( is_array( $sources ) && $sources ) { return $sources; }
	$url = (string) get_post_meta( $post_id, 'kb_source_url', true );
	if ( '' === $url ) { return array(); }
	$publisher = (string) get_post_meta( $post_id, 'kb_source_publisher', true );
	return array( array(
		'url'       => $url,
		'title'     => (string) get_post_meta( $post_id, 'kb_source_title', true ),
		'publisher' => '' !== $publisher ? $publisher : kpopblog_collector_publisher_from_url( $url ),
	) );
}

/** Credit another outlet on an existing story instead of publishing a duplicate. */
function kpopblog_collector_add_source( $post_id, array $item ) {
	$sources = kpopblog_collector_post_sources( $post_id );
	foreach ( $sources as $source ) {
		if ( $source['publisher'] === $item['publisher'] || $source['url'] === $item['url'] ) { return false; }
	}
	$sources[] = array( 'url' => $item['url'], 'title' => $item['title'], 'publisher' => $item['publisher'] );
	$sources = array_slice( $sources, 0, 6 );
	update_post_meta( $post_id, 'kb_sources', $sources );
	update_post_meta( $post_id, 'kb_source_urls', wp_list_pluck( $sources, 'url' ) );
	$artists = array_values( array_unique( array_merge( array_filter( (array) get_post_meta( $post_id, 'kb_related_artist_slugs', true ), 'strlen' ), $item['artists'] ) ) );
	update_post_meta( $post_id, 'kb_related_artist_slugs', array_slice( $artists, 0, 8 ) );
	if ( '' === (string) get_post_meta( $post_id, 'kb_external_image', true ) && '' !== $item['image'] ) {
		update_post_meta( $post_id, 'kb_external_image', $item['image'] );
	}
	return true;
}

/* ---------- on-site article footer ---------- */

/**
 * Footer appended to collected briefs: more coverage of the same artist, the
 * forum thread, the next release, and a compact source credit. Internal links
 * come first; the credit is small text that opens in a new tab so the site
 * stays open.
 */
function kpopblog_collector_article_footer( WP_Post $post ) {
	$html = '';
	$artists = array_values( array_filter( (array) get_post_meta( $post->ID, 'kb_related_artist_slugs', true ), 'strlen' ) );
	$primary = $artists ? $artists[0] : '';
	$names = $primary && function_exists( 'kpopblog_artist_names_for_slugs' ) ? kpopblog_artist_names_for_slugs( array( $primary ) ) : array();
	$name = $names ? $names[0] : '';

	if ( '' !== $primary ) {
		$html .= kpopblog_artist_fact_box( $primary );
		$related = get_posts( array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'numberposts'  => 3,
			'post__not_in' => array( $post->ID ),
			'meta_query'   => array( array( 'key' => 'kb_related_artist_slugs', 'value' => '"' . $primary . '"', 'compare' => 'LIKE' ) ),
		) );
		if ( $related ) {
			$html .= '<h3>More on ' . esc_html( $name ?: $primary ) . '</h3><ul class="kb-related">';
			foreach ( $related as $item ) {
				$html .= '<li><a href="' . esc_url( home_url( '/news/' . $item->post_name ) ) . '">' . esc_html( kpopblog_decode_text_entities( get_the_title( $item ) ) ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		$upcoming = get_posts( array(
			'post_type'   => 'kb_comeback',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_key'    => 'kb_release_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array(
				'relation' => 'AND',
				array( 'key' => 'kb_artist_slug', 'value' => $primary ),
				array( 'key' => 'kb_release_at', 'value' => gmdate( 'Y-m-d' ), 'compare' => '>=' ),
			),
		) );
		if ( $upcoming ) {
			$release = strtotime( (string) get_post_meta( $upcoming[0]->ID, 'kb_release_at', true ) );
			$html .= '<p class="kb-next-release">Next release: <a href="' . esc_url( home_url( '/comebacks' ) ) . '">' . esc_html( get_the_title( $upcoming[0] ) ) . ( $release ? ' · ' . esc_html( gmdate( 'M j, Y', $release ) ) : '' ) . '</a></p>';
		}
	}

	$links = array();
	$thread_id = (int) get_post_meta( $post->ID, 'kb_discussion_thread_id', true );
	$thread = $thread_id ? get_post( $thread_id ) : null;
	if ( $thread && 'publish' === $thread->post_status ) {
		$links[] = '<a href="' . esc_url( home_url( '/thread/' . $thread->post_name ) ) . '">Join the fan discussion</a>';
	} else {
		$links[] = '<a href="' . esc_url( home_url( '/forum' ) ) . '">Talk about it in the forum</a>';
	}
	if ( '' !== $primary ) {
		$links[] = '<a href="' . esc_url( home_url( '/artist/' . $primary ) ) . '">' . esc_html( ( $name ?: $primary ) . ' profile & news' ) . '</a>';
	}
	$links[] = '<a href="' . esc_url( home_url( '/latest' ) ) . '">Latest K-pop news</a>';
	$html .= '<p class="kb-onsite-links">' . implode( ' · ', $links ) . '</p>';

	$sources = kpopblog_collector_post_sources( $post->ID );
	if ( $sources ) {
		$credits = array();
		foreach ( $sources as $source ) {
			$credits[] = '<a href="' . esc_url( $source['url'] ) . '" rel="nofollow noopener noreferrer" target="_blank">' . esc_html( $source['publisher'] ) . '</a>';
		}
		$html .= '<p class="kb-source-credit"><small>Summary based on reporting by ' . implode( ', ', $credits ) . '.</small></p>';
	}
	return '<div class="kb-article-extras">' . $html . '</div>';
}

/** "About {artist}" box built from the artist catalogue: bio, agency, debut, fandom, members. */
function kpopblog_artist_fact_box( $slug ) {
	$artist = function_exists( 'kpopblog_get_artist_by_slug' ) ? kpopblog_get_artist_by_slug( $slug ) : null;
	if ( ! $artist ) { return ''; }
	$name = kpopblog_decode_text_entities( get_the_title( $artist ) );
	$facts = array();
	$agency = (string) get_post_meta( $artist->ID, 'kb_agency', true );
	$debut  = (string) get_post_meta( $artist->ID, 'kb_debut_date', true );
	$fandom = (string) get_post_meta( $artist->ID, 'kb_fandom_name', true );
	$korean = (string) get_post_meta( $artist->ID, 'kb_korean_name', true );
	if ( '' !== $korean ) { $facts[] = 'Korean name: ' . esc_html( $korean ); }
	if ( '' !== $agency ) { $facts[] = 'Agency: ' . esc_html( $agency ); }
	if ( '' !== $debut && strtotime( $debut ) ) { $facts[] = 'Debut: ' . esc_html( gmdate( 'F j, Y', strtotime( $debut ) ) ); }
	if ( '' !== $fandom ) { $facts[] = 'Fandom: ' . esc_html( $fandom ); }
	$members = get_posts( array( 'post_type' => 'kb_member', 'post_status' => 'publish', 'numberposts' => 20, 'meta_key' => 'kb_group_slug', 'meta_value' => $slug, 'orderby' => 'ID', 'order' => 'ASC' ) );
	if ( $members ) {
		$facts[] = 'Members: ' . esc_html( implode( ', ', array_map( function ( $member ) { return kpopblog_decode_text_entities( get_the_title( $member ) ); }, $members ) ) );
	}
	$bio = trim( wp_strip_all_tags( $artist->post_content ) );
	if ( '' === $bio && ! $facts ) { return ''; }
	$html = '<h3>About ' . esc_html( $name ) . '</h3>';
	if ( '' !== $bio ) { $html .= '<p>' . esc_html( $bio ) . '</p>'; }
	if ( $facts ) { $html .= '<ul class="kb-facts"><li>' . implode( '</li><li>', $facts ) . '</li></ul>'; }
	return $html;
}

/** Rendered article body: stored summary plus the on-site footer for collected briefs. */
function kpopblog_render_article_content( WP_Post $post ) {
	$content = apply_filters( 'the_content', $post->post_content );
	if ( in_array( (string) get_post_meta( $post->ID, 'kb_source', true ), array( 'aggregated', 'ai-brief' ), true ) ) {
		$content .= kpopblog_collector_article_footer( $post );
	}
	return $content;
}
