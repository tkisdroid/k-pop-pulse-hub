<?php
/**
 * WP-CLI content seeder — fills WordPress with three years of realistic,
 * varied editorial and community content so the site reads as an established,
 * actively operated publication.
 *
 * Usage:
 *   wp kpopblog seed            # seed once (idempotent; skips if already seeded)
 *   wp kpopblog seed --fresh    # wipe seeded content and rebuild from scratch
 *
 * Everything is generated deterministically from a fixed RNG seed so repeated
 * runs produce the same catalogue. Featured images are generated locally with
 * GD (gradient + artist name) so no external assets are required.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }

class KpopBlog_Seeder {

	/** @var int Deterministic RNG state. */
	private $rng = 20260728;

	private $now_ts;
	private $start_ts;

	/** artist slug => attachment id for the generated cover image. */
	private $artist_images = array();
	/** slug => artist row. */
	private $artists = array();
	/** user logins => user id. */
	private $authors = array();
	private $community_users = array();

	public function __construct() {
		$this->now_ts   = current_time( 'timestamp', true );
		$this->start_ts = strtotime( '2023-07-01 00:00:00' );
	}

	/* ---------------- deterministic RNG ---------------- */

	private function rand() {
		// xorshift32 — stable across runs and PHP versions.
		$x = $this->rng;
		$x ^= ( $x << 13 ) & 0xFFFFFFFF;
		$x ^= ( $x >> 17 );
		$x ^= ( $x << 5 ) & 0xFFFFFFFF;
		$this->rng = $x & 0xFFFFFFFF;
		return $this->rng;
	}

	private function rint( $min, $max ) {
		return $min + ( $this->rand() % ( $max - $min + 1 ) );
	}

	private function pick( $arr ) {
		return $arr[ $this->rand() % count( $arr ) ];
	}

	/** A timestamp somewhere in the last ~3 years, biased slightly toward recent. */
	private function random_ts() {
		$span = $this->now_ts - $this->start_ts;
		// Square-root bias keeps a healthy spread while favouring recency.
		$frac = sqrt( $this->rand() % 10000 / 10000 );
		return $this->now_ts - (int) ( $span * $frac );
	}

	private function ts_to_post_date( $ts ) {
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/* ---------------- images ---------------- */

	private function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) !== 6 ) { return array( 80, 80, 120 ); }
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	private function make_image( $slug, $c1, $c2, $label ) {
		$w = 1280; $h = 800;
		$img = imagecreatetruecolor( $w, $h );
		list( $r1, $g1, $b1 ) = $this->hex_to_rgb( $c1 );
		list( $r2, $g2, $b2 ) = $this->hex_to_rgb( $c2 );
		for ( $y = 0; $y < $h; $y++ ) {
			$t = $y / $h;
			$r = (int) ( $r1 + ( $r2 - $r1 ) * $t );
			$g = (int) ( $g1 + ( $g2 - $g1 ) * $t );
			$b = (int) ( $b1 + ( $b2 - $b1 ) * $t );
			$color = imagecolorallocate( $img, $r, $g, $b );
			imageline( $img, 0, $y, $w, $y, $color );
		}
		// Soft diagonal accent band for a little depth.
		$accent = imagecolorallocatealpha( $img, 255, 255, 255, 118 );
		imagefilledpolygon( $img, array( 0, $h, $w * 0.55, $h, 0, $h * 0.35 ), $accent );

		$white = imagecolorallocate( $img, 255, 255, 255 );
		$text  = strtoupper( $label );
		$font  = 5; // built-in, largest
		$tw    = imagefontwidth( $font ) * strlen( $text );
		$th    = imagefontheight( $font );
		imagestring( $img, $font, (int) ( ( $w - $tw ) / 2 ), (int) ( ( $h - $th ) / 2 ), $text, $white );

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'kpopblog-seed';
		if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }
		$file = trailingslashit( $dir ) . $slug . '.png';
		imagepng( $img, $file );
		imagedestroy( $img );

		$url  = trailingslashit( $upload['baseurl'] ) . 'kpopblog-seed/' . $slug . '.png';
		$type = 'image/png';

		$attachment = array(
			'post_mime_type' => $type,
			'post_title'     => $label,
			'post_name'      => 'seed-img-' . $slug,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $file );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) { return 0; }
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$meta = wp_generate_attachment_metadata( $attach_id, $file );
		wp_update_attachment_metadata( $attach_id, $meta );
		return (int) $attach_id;
	}

	/* ---------------- dataset ---------------- */

	private function artist_data() {
		// slug, name, korean, type, agency, debut, generation, fandom, c1, c2, followers, bio, songs[], albums[]
		return array(
			array( 'bts', 'BTS', '방탄소년단', 'boy_group', 'BIGHIT MUSIC (HYBE)', '2013-06-13', 3, 'ARMY', '#7B68EE', '#3A1F8E', 75000000,
				'Seven-member group whose genre-spanning catalog and global advocacy made them the first K-pop act to top the Billboard 200 and headline UN events.',
				array( 'Dynamite', 'Butter', 'Permission to Dance', 'Spring Day', 'Boy With Luv', 'Fake Love', 'IDOL', 'ON', 'Life Goes On', 'Yet To Come' ),
				array( 'Map of the Soul: 7', 'BE', 'Love Yourself: Tear', 'Wings', 'Proof' ) ),
			array( 'blackpink', 'BLACKPINK', '블랙핑크', 'girl_group', 'YG Entertainment', '2016-08-08', 3, 'BLINK', '#FF1493', '#111111', 56000000,
				'Four-member group that broke records as the first K-pop girl group to headline Coachella and tour stadiums worldwide.',
				array( 'Pink Venom', 'Shut Down', 'How You Like That', 'DDU-DU DDU-DU', 'Kill This Love', 'As If It\'s Your Last', 'BOOMBAYAH', 'Typa Girl', 'The Girls' ),
				array( 'BORN PINK', 'THE ALBUM', 'SQUARE UP' ) ),
			array( 'newjeans', 'NewJeans', '뉴진스', 'girl_group', 'ADOR', '2022-07-22', 4, 'Bunnies', '#87CEEB', '#1E3A8A', 12500000,
				'Five-member group that redefined Y2K nostalgia in 4th generation K-pop with viral debut tracks.',
				array( 'Attention', 'Hype Boy', 'Ditto', 'OMG', 'Super Shy', 'ETA', 'Cool With You', 'Get Up', 'Bubble Gum', 'How Sweet' ),
				array( 'Get Up', 'New Jeans', 'How Sweet' ) ),
			array( 'le-sserafim', 'LE SSERAFIM', '르세라핌', 'girl_group', 'Source Music (HYBE)', '2022-05-02', 4, 'FEARNOT', '#6B5B95', '#1A1147', 9800000,
				'Five-member group whose name is an anagram of "I\'m Fearless," known for confident anthems.',
				array( 'FEARLESS', 'ANTIFRAGILE', 'UNFORGIVEN', 'EASY', 'CRAZY', 'HOT', 'Perfect Night', 'Eve, Psyche & The Bluebeard\'s wife', 'Smart' ),
				array( 'UNFORGIVEN', 'EASY', 'CRAZY', 'FEARLESS' ) ),
			array( 'aespa', 'aespa', '에스파', 'girl_group', 'SM Entertainment', '2020-11-17', 4, 'MY', '#00E5FF', '#0A2540', 15200000,
				'Four-member group built around a hybrid metaverse concept with a string of digital-chart hits.',
				array( 'Supernova', 'Armageddon', 'Spicy', 'Savage', 'Next Level', 'Black Mamba', 'Girls', 'Drama', 'Whiplash', 'Illusion' ),
				array( 'Armageddon', 'MY WORLD', 'Girls', 'Whiplash' ) ),
			array( 'ive', 'IVE', '아이브', 'girl_group', 'Starship Entertainment', '2021-12-01', 4, 'DIVE', '#4B0082', '#FFD700', 11400000,
				'Six-member group that debuted with "ELEVEN" and dominated 2022-2024 charts.',
				array( 'ELEVEN', 'LOVE DIVE', 'After LIKE', 'I AM', 'Kitsch', 'Baddie', 'HEYA', 'Accendio', 'Off The Record', 'Either Way' ),
				array( 'I\'ve MINE', 'I\'ve IVE', 'ELEVEN' ) ),
			array( 'stray-kids', 'Stray Kids', '스트레이 키즈', 'boy_group', 'JYP Entertainment', '2018-03-25', 4, 'STAY', '#DC143C', '#1A1A1A', 22000000,
				'Self-producing eight-member group that scored five consecutive No. 1 debuts on the Billboard 200.',
				array( 'MANIAC', 'God\'s Menu', 'S-Class', 'LALALALA', 'Chk Chk Boom', 'Thunderous', 'CASE 143', 'MIROH', 'Back Door', 'Walkin On Water' ),
				array( '5-STAR', 'ROCK-STAR', 'ATE', 'ODDINARY', 'MAXIDENT' ) ),
			array( 'twice', 'TWICE', '트와이스', 'girl_group', 'JYP Entertainment', '2015-10-20', 3, 'ONCE', '#FF69B4', '#FF8C00', 18700000,
				'Nine-member group whose discography set the template for late-2010s K-pop pop.',
				array( 'Feel Special', 'I CAN\'T STOP ME', 'TT', 'FANCY', 'What is Love?', 'Cheer Up', 'Talk that Talk', 'Set Me Free', 'One Spark', 'MOONLIGHT SUNRISE' ),
				array( 'Formula of Love', 'Eyes wide open', 'Feel Special', 'READY TO BE' ) ),
			array( 'seventeen', 'SEVENTEEN', '세븐틴', 'boy_group', 'PLEDIS Entertainment (HYBE)', '2015-05-26', 3, 'CARAT', '#FFB6C1', '#7B4B94', 20300000,
				'Self-producing 13-member group split into vocal, hip-hop, and performance units.',
				array( 'Super', 'MAESTRO', 'HOT', 'Rock with you', 'Left & Right', 'Fear', 'CLAP', 'Very Nice', 'Don\'t Wanna Cry', 'God of Music' ),
				array( 'FML', 'SEVENTEENTH HEAVEN', '17 IS RIGHT HERE', 'An Ode' ) ),
			array( 'itzy', 'ITZY', '있지', 'girl_group', 'JYP Entertainment', '2019-02-12', 4, 'MIDZY', '#FF4500', '#2E0066', 9200000,
				'Five-member group that debuted with "DALLA DALLA" and built a catalog around self-love anthems.',
				array( 'DALLA DALLA', 'WANNABE', 'Not Shy', 'LOCO', 'SNEAKERS', 'CAKE', 'UNTOUCHABLE', 'ICY', 'Cheshire', 'Mr. Vampire' ),
				array( 'CRAZY IN LOVE', 'CHECKMATE', 'BORN TO BE', 'IT\'z ICY' ) ),
			array( 'riize', 'RIIZE', '라이즈', 'boy_group', 'SM Entertainment', '2023-09-04', 5, 'BRIIZE', '#FFD700', '#0A1F44', 4500000,
				'Seven-member group blending emo-pop and R&B.',
				array( 'Get A Guitar', 'Memories', 'Love 119', 'Boom Boom Bass', 'Impossible', 'Talk Saxy', 'Siren', 'Fly Up', 'Show Me Love' ),
				array( 'RIIZING', 'Get A Guitar' ) ),
			array( 'enhypen', 'ENHYPEN', '엔하이픈', 'boy_group', 'BELIFT LAB (HYBE)', '2020-11-30', 4, 'ENGENE', '#B22222', '#0A0A0A', 11800000,
				'Seven-member group formed via "I-LAND."',
				array( 'Bite Me', 'Sweet Venom', 'XO (Only If You Say Yes)', 'Drunk-Dazed', 'FEVER', 'Polaroid Love', 'Given-Taken', 'No Doubt', 'Brought The Heat Back' ),
				array( 'ROMANCE: UNTOLD', 'DIMENSION: DILEMMA', 'MANIFESTO: DAY 1', 'BORDER: CARNIVAL' ) ),
			array( 'iu', 'IU', '아이유', 'soloist', 'EDAM Entertainment', '2008-09-18', 2, 'UAENA', '#E91E63', '#4A0033', 28400000,
				'Singer-songwriter and actress widely regarded as Korea\'s "nation\'s little sister."',
				array( 'Love wins all', 'eight', 'Blueming', 'Celebrity', 'Palette', 'Good Day', 'LILAC', 'strawberry moon', 'Holssi', 'Ending Scene' ),
				array( 'The Winning', 'LILAC', 'Palette', 'Love poem' ) ),
			array( 'txt', 'TOMORROW X TOGETHER', '투모로우바이투게더', 'boy_group', 'BIGHIT MUSIC (HYBE)', '2019-03-04', 4, 'MOA', '#87CEFA', '#1E3A8A', 14900000,
				'Five-member coming-of-age concept group.',
				array( 'Sugar Rush Ride', 'Chasing That Feeling', 'Deja Vu', '0X1=LOVESONG', 'Blue Hour', 'Run Away', 'Good Boy Gone Bad', 'Over The Moon', 'Back for More' ),
				array( 'The Name Chapter: FREEFALL', 'minisode 3: TOMORROW', 'The Chaos Chapter: FREEZE', 'The Dream Chapter: MAGIC' ) ),
			array( 'gidle', '(G)I-DLE', '(여자)아이들', 'girl_group', 'CUBE Entertainment', '2018-05-02', 4, 'NEVERLAND', '#C71585', '#2D0033', 10100000,
				'Self-producing five-member group.',
				array( 'TOMBOY', 'Queencard', 'Super Lady', 'Nxde', 'LATATA', 'Uh-Oh', 'LION', 'Wife', 'Klaxon', 'Fate' ),
				array( '2', 'I feel', 'I NEVER DIE', 'I burn' ) ),
			array( 'zerobaseone', 'ZEROBASEONE', '제로베이스원', 'boy_group', 'WAKEONE', '2023-07-10', 5, 'ZEROSE', '#1E90FF', '#0A0033', 5600000,
				'Nine-member group formed through "Boys Planet."',
				array( 'In Bloom', 'CRUSH', 'SWEAT', 'Feel the POP', 'GOOD SO BAD', 'KILL THE ROMEO', 'YOUTH IN THE SHADE', 'Our Season' ),
				array( 'YOUTH IN THE SHADE', 'MELTING POINT', 'You had me at HELLO', 'CINEMA PARADISE' ) ),
		);
	}

	private function member_data() {
		// group slug => list of [stage, full, korean, birthday, positions[], mbti]
		return array(
			'bts' => array(
				array( 'RM', 'Kim Nam-joon', '김남준', '1994-09-12', array( 'Leader', 'Rapper' ), 'ENFP' ),
				array( 'Jin', 'Kim Seok-jin', '김석진', '1992-12-04', array( 'Vocalist' ), 'INFP' ),
				array( 'SUGA', 'Min Yoon-gi', '민윤기', '1993-03-09', array( 'Rapper' ), 'INTP' ),
				array( 'j-hope', 'Jung Ho-seok', '정호석', '1994-02-18', array( 'Rapper', 'Dancer' ), 'ESFJ' ),
				array( 'Jimin', 'Park Ji-min', '박지민', '1995-10-13', array( 'Vocalist', 'Dancer' ), 'ENFJ' ),
				array( 'V', 'Kim Tae-hyung', '김태형', '1995-12-30', array( 'Vocalist' ), 'ENFP' ),
				array( 'Jungkook', 'Jeon Jung-kook', '전정국', '1997-09-01', array( 'Main Vocalist', 'Maknae' ), 'ISFP' ),
			),
			'blackpink' => array(
				array( 'Jisoo', 'Kim Ji-soo', '김지수', '1995-01-03', array( 'Vocalist' ), 'INFJ' ),
				array( 'Jennie', 'Jennie Kim', '김제니', '1996-01-16', array( 'Rapper', 'Vocalist' ), 'INFP' ),
				array( 'Rosé', 'Park Chae-young', '박채영', '1997-02-11', array( 'Main Vocalist' ), 'ENFP' ),
				array( 'Lisa', 'Lalisa Manobal', '라리사 마노반', '1997-03-27', array( 'Main Dancer', 'Rapper' ), 'ESFP' ),
			),
			'newjeans' => array(
				array( 'Minji', 'Kim Min-ji', '김민지', '2004-05-07', array( 'Vocalist' ), 'ISTP' ),
				array( 'Hanni', 'Hanni Pham', '하니 팜', '2004-10-06', array( 'Vocalist' ), 'INFP' ),
				array( 'Danielle', 'Danielle Marsh', '다니엘 마쉬', '2005-04-11', array( 'Vocalist' ), 'ENFP' ),
				array( 'Haerin', 'Kang Hae-rin', '강해린', '2006-05-15', array( 'Vocalist' ), 'ISTP' ),
				array( 'Hyein', 'Lee Hye-in', '이혜인', '2008-04-21', array( 'Vocalist', 'Maknae' ), 'INFP' ),
			),
			'le-sserafim' => array(
				array( 'Sakura', 'Miyawaki Sakura', '미야와키 사쿠라', '1998-03-19', array( 'Vocalist' ), 'INFJ' ),
				array( 'Kim Chaewon', 'Kim Chae-won', '김채원', '2000-08-01', array( 'Leader', 'Vocalist' ), 'ESFP' ),
				array( 'Huh Yunjin', 'Huh Yun-jin', '허윤진', '2001-10-08', array( 'Main Vocalist' ), 'ENFJ' ),
				array( 'Kazuha', 'Nakamura Kazuha', '나카무라 카즈하', '2003-08-09', array( 'Dancer', 'Vocalist' ), 'INFP' ),
				array( 'Hong Eunchae', 'Hong Eun-chae', '홍은채', '2006-11-10', array( 'Vocalist', 'Maknae' ), 'ESFP' ),
			),
			'aespa' => array(
				array( 'Karina', 'Yu Ji-min', '유지민', '2000-04-11', array( 'Leader', 'Dancer' ), 'ENFP' ),
				array( 'Giselle', 'Uchinaga Aeri', '우치나가 아에리', '2000-10-30', array( 'Rapper' ), 'INFP' ),
				array( 'Winter', 'Kim Min-jeong', '김민정', '2001-01-01', array( 'Main Vocalist' ), 'ISTP' ),
				array( 'Ningning', 'Ning Yizhuo', '닝이줘', '2002-10-23', array( 'Main Vocalist', 'Maknae' ), 'ENFP' ),
			),
			'ive' => array(
				array( 'An Yujin', 'An Yu-jin', '안유진', '2003-09-01', array( 'Leader', 'Vocalist' ), 'ISTP' ),
				array( 'Gaeul', 'Kim Ga-eul', '김가을', '2002-09-24', array( 'Rapper' ), 'INFP' ),
				array( 'Rei', 'Naoi Rei', '나오이 레이', '2004-02-03', array( 'Rapper' ), 'INFP' ),
				array( 'Jang Wonyoung', 'Jang Won-young', '장원영', '2004-08-31', array( 'Vocalist' ), 'ENFP' ),
				array( 'Liz', 'Kim Ji-won', '김지원', '2004-11-21', array( 'Main Vocalist' ), 'ISFP' ),
				array( 'Leeseo', 'Lee Hyun-seo', '이현서', '2007-02-21', array( 'Vocalist', 'Maknae' ), 'ESFP' ),
			),
			'stray-kids' => array(
				array( 'Bang Chan', 'Bang Chan', '방찬', '1997-10-03', array( 'Leader', 'Producer' ), 'ENFP' ),
				array( 'Changbin', 'Seo Chang-bin', '서창빈', '1999-08-11', array( 'Rapper', 'Producer' ), 'ENFP' ),
				array( 'Han', 'Han Ji-sung', '한지성', '2000-09-14', array( 'Rapper', 'Producer' ), 'INFP' ),
				array( 'Hyunjin', 'Hwang Hyun-jin', '황현진', '2000-03-20', array( 'Dancer', 'Rapper' ), 'INFP' ),
				array( 'Felix', 'Lee Felix', '이용복', '2000-09-15', array( 'Dancer', 'Vocalist' ), 'ENFP' ),
				array( 'Seungmin', 'Kim Seung-min', '김승민', '2000-09-22', array( 'Main Vocalist' ), 'ISTP' ),
				array( 'I.N', 'Yang Jeong-in', '양정인', '2001-02-08', array( 'Vocalist', 'Maknae' ), 'ISFP' ),
			),
			'itzy' => array(
				array( 'Yeji', 'Hwang Ye-ji', '황예지', '2000-05-26', array( 'Leader', 'Dancer' ), 'ISTP' ),
				array( 'Lia', 'Choi Ji-su', '최지수', '2000-07-21', array( 'Main Vocalist' ), 'INFP' ),
				array( 'Ryujin', 'Shin Ryu-jin', '신류진', '2001-04-17', array( 'Rapper', 'Dancer' ), 'ISTP' ),
				array( 'Chaeryeong', 'Lee Chae-ryeong', '이채령', '2001-06-05', array( 'Main Dancer' ), 'ISFP' ),
				array( 'Yuna', 'Shin Yu-na', '신유나', '2003-12-09', array( 'Vocalist', 'Maknae' ), 'ENFP' ),
			),
			'enhypen' => array(
				array( 'Jungwon', 'Yang Jung-won', '양정원', '2004-02-09', array( 'Leader', 'Vocalist' ), 'ISTP' ),
				array( 'Heeseung', 'Lee Hee-seung', '이희승', '2001-10-15', array( 'Main Vocalist' ), 'INFP' ),
				array( 'Jay', 'Park Jong-seong', '박종성', '2002-04-20', array( 'Rapper' ), 'ENFP' ),
				array( 'Jake', 'Jake Sim', '심재윤', '2002-11-15', array( 'Vocalist' ), 'ENFP' ),
				array( 'Sunghoon', 'Park Sung-hoon', '박성훈', '2002-12-08', array( 'Vocalist', 'Dancer' ), 'ISTP' ),
				array( 'Sunoo', 'Kim Sun-oo', '김선우', '2003-06-24', array( 'Vocalist' ), 'ENFP' ),
				array( 'Ni-ki', 'Nishimura Riki', '니시무라 리키', '2005-12-09', array( 'Main Dancer', 'Maknae' ), 'ISTP' ),
			),
			'txt' => array(
				array( 'Soobin', 'Choi Soo-bin', '최수빈', '2000-12-05', array( 'Leader', 'Vocalist' ), 'ISFP' ),
				array( 'Yeonjun', 'Choi Yeon-jun', '최연준', '1999-09-13', array( 'Rapper', 'Dancer' ), 'ENFP' ),
				array( 'Beomgyu', 'Choi Beom-gyu', '최범규', '2001-03-13', array( 'Vocalist' ), 'INFP' ),
				array( 'Taehyun', 'Kang Tae-hyun', '강태현', '2002-02-05', array( 'Vocalist' ), 'ISTP' ),
				array( 'Huening Kai', 'Kai Kamal Huening', '카이 카말 휴닝', '2002-08-14', array( 'Vocalist', 'Maknae' ), 'ENFP' ),
			),
			'gidle' => array(
				array( 'Miyeon', 'Cho Mi-yeon', '조미연', '1997-01-31', array( 'Main Vocalist' ), 'ISFP' ),
				array( 'Minnie', 'Nicha Yontararak', '니차 욘타라락', '1997-10-23', array( 'Main Vocalist' ), 'INFP' ),
				array( 'Soyeon', 'Jeon So-yeon', '전소연', '1998-08-26', array( 'Leader', 'Rapper', 'Producer' ), 'ENFP' ),
				array( 'Yuqi', 'Song Yu-qi', '송우기', '1999-09-23', array( 'Vocalist' ), 'ENFP' ),
				array( 'Shuhua', 'Yeh Shu-hua', '예슈화', '2000-01-06', array( 'Vocalist', 'Maknae' ), 'ISFP' ),
			),
			'zerobaseone' => array(
				array( 'Sung Han-bin', 'Sung Han-bin', '성한빈', '2001-06-13', array( 'Leader', 'Dancer' ), 'ENFP' ),
				array( 'Kim Ji-woong', 'Kim Ji-woong', '김지웅', '1998-12-14', array( 'Vocalist' ), 'INFP' ),
				array( 'Zhang Hao', 'Zhang Hao', '장하오', '2000-07-25', array( 'Main Vocalist' ), 'ENFP' ),
				array( 'Seok Matthew', 'Seok Matthew', '석매튜', '2002-05-28', array( 'Vocalist' ), 'ENFP' ),
				array( 'Kim Tae-rae', 'Kim Tae-rae', '김태래', '2003-07-14', array( 'Main Vocalist' ), 'ISFP' ),
				array( 'Ricky', 'Ricky', '리키', '2004-05-20', array( 'Dancer' ), 'ISTP' ),
				array( 'Kim Gyu-vin', 'Kim Gyu-vin', '김규빈', '2004-08-30', array( 'Rapper' ), 'ENFP' ),
				array( 'Park Gun-wook', 'Park Gun-wook', '박건욱', '2005-01-10', array( 'Rapper' ), 'ISTP' ),
				array( 'Han Yu-jin', 'Han Yu-jin', '한유진', '2007-03-20', array( 'Vocalist', 'Maknae' ), 'INFP' ),
			),
			'riize' => array(
				array( 'Shotaro', 'Osaki Shotaro', '오사키 쇼타로', '2000-11-25', array( 'Dancer' ), 'ENFP' ),
				array( 'Eunseok', 'Song Eun-seok', '송은석', '2001-03-19', array( 'Vocalist' ), 'ISTP' ),
				array( 'Sungchan', 'Jung Sung-chan', '정성찬', '2001-09-13', array( 'Rapper' ), 'ENFP' ),
				array( 'Wonbin', 'Park Won-bin', '박원빈', '2002-03-02', array( 'Vocalist' ), 'INFP' ),
				array( 'Sohee', 'Lee So-hee', '이소희', '2003-11-21', array( 'Main Vocalist' ), 'ISFP' ),
				array( 'Anton', 'Anton Lee', '안톤 리', '2004-03-21', array( 'Vocalist', 'Maknae' ), 'ENFP' ),
			),
		);
	}

	private function author_data() {
		// login, display name, email, bio, country
		return array(
			array( 'editor.han', 'Hana Editor', 'hana@thekpopblog.test', 'Editor-in-chief covering K-pop since the second generation.', 'South Korea' ),
			array( 'minjun.k', 'Minjun Kim', 'minjun@thekpopblog.test', 'Charts analyst and Billboard correspondent.', 'South Korea' ),
			array( 'sofia.l', 'Sofia Lindqvist', 'sofia@thekpopblog.test', 'Global touring and concert reviewer based in Stockholm.', 'Sweden' ),
			array( 'dee.r', 'Dee Ramasamy', 'dee@thekpopblog.test', 'Industry and business writer covering labels and contracts.', 'Singapore' ),
			array( 'yuki.t', 'Yuki Tanaka', 'yuki@thekpopblog.test', 'Japan-market reporter and comeback reviewer.', 'Japan' ),
			array( 'marco.p', 'Marco Pereira', 'marco@thekpopblog.test', 'Latin America correspondent and fan-culture columnist.', 'Brazil' ),
		);
	}

	private function community_user_data() {
		$names = array(
			array( 'bunny_forever', 'NewJeans since Attention', 'South Korea' ),
			array( 'carat_light', 'SEVENTEEN stan, hyper unit bias', 'Philippines' ),
			array( 'stay_zone', 'Stray Kids everywhere', 'United States' ),
			array( 'once_upon', 'TWICE from debut day one', 'Japan' ),
			array( 'army_bomb', 'BTS 7 OT4EVER', 'Indonesia' ),
			array( 'blink_black', 'BLACKPINK in your area', 'Thailand' ),
			array( 'my_aespa', 'aespa metaverse believer', 'Brazil' ),
			array( 'dive_ive', 'IVE Wonyoung bias', 'Vietnam' ),
			array( 'fearnot_1', 'LE SSERAFIM fearless', 'Mexico' ),
			array( 'midzy_itzy', 'ITZY all-rounder lover', 'Malaysia' ),
			array( 'briize_wave', 'RIIZE emo-pop enjoyer', 'South Korea' ),
			array( 'engene_blood', 'ENHYPEN vampire concept', 'France' ),
			array( 'uaena_iu', 'IU nation little sister', 'South Korea' ),
			array( 'moa_together', 'TXT tomorrow x together', 'Canada' ),
			array( 'neverland_idle', '(G)I-DLE self-produced truth', 'United Kingdom' ),
			array( 'zerose_one', 'ZEROBASEONE boys planet', 'Australia' ),
			array( 'kpop_stats', 'Circle chart data nerd', 'South Korea' ),
			array( 'mvp_streamer', 'Streaming party organizer', 'United States' ),
			array( 'photocard_hoarder', 'PC collector, 1200 and counting', 'Germany' ),
			array( 'concert_junkie', '47 concerts and counting', 'Japan' ),
			array( 'bias_wrecker99', 'everyone is my bias', 'Spain' ),
			array( 'gg_critic', 'girl group deep cuts reviewer', 'Netherlands' ),
			array( 'boygroup_guy', 'boy group choreography analyst', 'India' ),
			array( 'debut_tracked', 'rookie watcher since 2019', 'Singapore' ),
			array( 'lightstick_army', 'full shelf of lightsticks', 'Taiwan' ),
			array( 'fancam_archive', '4K fancam archivist', 'South Korea' ),
		);
		return $names;
	}

	/* ---------------- content text ---------------- */

	private function article_templates() {
		// Each returns [title, subtitle, category, body] given (artist, song, album).
		return array(
			function ( $a, $song, $album ) {
				return array(
					sprintf( "%s's '%s' storms global charts weeks after release", $a['name'], $song ),
					sprintf( "The %s lead single keeps climbing streaming and sales charts worldwide.", $album ),
					'chart',
					sprintf( "<p>%s have extended their chart dominance as '%s' continues to climb both domestic and international rankings. The track, drawn from '%s,' has held a top-ten position on Korea's Circle Digital Chart for multiple consecutive weeks.</p><p>Overseas, the single has accumulated tens of millions of streams on major platforms, while the physical release of '%s' posted a strong first-week total. Analysts credit the sustained run to a coordinated streaming push from %s and steady radio airplay.</p><h2>What comes next</h2><p>The group is expected to continue promotions through the month before pivoting to tour rehearsals.</p>", $a['name'], $song, $album, $album, $a['fandom'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "%s announce '%s' era with first teaser drop", $a['name'], $album ),
					sprintf( "Concept photos and a schedule poster hint at a bold new direction for the %s act.", $a['type'] === 'soloist' ? 'solo' : 'group' ),
					'comeback',
					sprintf( "<p>%s have officially kicked off the comeback cycle for '%s,' unveiling a schedule poster and the first set of concept photos. The imagery signals a sharper, more cinematic direction compared with the group's previous release.</p><p>Fans were quick to dissect the teaser for clues, with %s trending worldwide within hours of the announcement. The title track is rumored to be '%s,' though the agency has not confirmed.</p><h2>Release window</h2><p>The full album arrives at the end of the month, followed by a comeback showcase and a run of music-show performances.</p>", $a['name'], $album, $a['fandom'], $song ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "Review: %s deliver a career highlight with '%s'", $a['name'], $song ),
					'A track-by-track look at a release that rewards repeat listens.',
					'review',
					sprintf( "<p>There are releases that maintain a formula, and there are releases that push an artist forward. '%s' firmly belongs to the latter category for %s. The production is confident, the vocal performances are some of the strongest of their career, and the sequencing keeps the energy moving.</p><p>The title track '%s' is the obvious centerpiece, but the B-sides on '%s' hold up remarkably well, giving the project a coherence that single-driven releases often lack.</p><h2>Verdict</h2><p>A focused, ambitious record that stands among the year's strongest K-pop releases.</p>", $album, $a['name'], $song, $album ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "%s to headline world tour spanning three continents", $a['name'] ),
					'Dates across Asia, North America and Europe confirmed for the coming year.',
					'tour',
					sprintf( "<p>%s will embark on their largest world tour to date, the agency confirmed, with stops across Asia, North America and Europe. The production is built around the group's most recent work, including '%s' and tracks from '%s.'</p><p>Presale access opens first for %s membership holders before the general onsale. Several markets are already expected to sell out quickly given the group's touring demand.</p><h2>Fan reaction</h2><p>Touring has become a defining part of the %s story, and the expanded routing answers long-standing requests from international fans.</p>", $a['name'], $song, $album, $a['fandom'], $a['name'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "Interview: %s on growth, pressure and the meaning of '%s'", $a['name'], $song ),
					'A candid conversation about craft, expectations and life on the road.',
					'interview',
					sprintf( "<p>In a rare long-form sit-down, members of %s opened up about the making of '%s' and the pressures that come with operating at the top of the industry.</p><p>\"We think a lot about what we want to say with each release,\" one member explained. \"'%s' came from a place of honesty, and I think %s can feel that.\" The conversation ranged from songwriting process to the emotional toll of constant touring.</p><h2>Looking ahead</h2><p>The group says the next chapter will lean even further into self-production.</p>", $a['name'], $album, $song, $a['fandom'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "%s' agency addresses speculation around %s activities", $a['agency'], $a['name'] ),
					'The label issued a statement clarifying plans for the coming months.',
					'news',
					sprintf( "<p>%s released an official statement addressing recent speculation about %s's schedule and future activities. The agency emphasized that plans for '%s'-era promotions remain on track and asked fans to rely only on confirmed announcements.</p><p>\"We appreciate the passion of %s,\" the statement read. \"We will continue to support the artists so they can focus on their music and performances.\" The clarification follows a week of intense online discussion.</p>", $a['agency'], $a['name'], $album, $a['fandom'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "Behind the scenes: how %s made '%s'", $a['name'], $song ),
					'Producers and choreographers break down the creative process.',
					'behind',
					sprintf( "<p>A new behind-the-scenes feature pulls back the curtain on the creation of '%s,' one of %s's most discussed tracks. From the initial demo to the final mix, the piece traces how the song evolved over months of workshops.</p><p>The choreography team described building the performance around the track's dynamic shifts, while the vocal directors highlighted the recording sessions for '%s.' The result is a detailed look at the craft that %s rarely get to see.</p>", $song, $a['name'], $album, $a['fandom'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "%s make history as '%s' crosses a streaming milestone", $a['name'], $song ),
					'The achievement cements the group\'s place among the generation\'s leaders.',
					'chart',
					sprintf( "<p>'%s' has crossed a major streaming milestone, making %s one of the few acts to reach the mark this quickly. The song, from '%s,' has become a staple on editorial playlists and continues to draw new listeners months after release.</p><p>Industry observers note that the longevity of the track — rather than a sharp opening spike — is what makes the achievement notable. %s have turned sustained streaming into a reliable engine for chart success.</p>", $song, $a['name'], $album, $a['name'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "Opinion: why %s's '%s' era matters more than the numbers", $a['name'], $album ),
					'Beyond the chart data, a release that shifted expectations for the group.',
					'opinion',
					sprintf( "<p>It would be easy to frame '%s' purely through its commercial performance, but that would miss the point. For %s, the '%s' era represents a creative statement — a deliberate move away from safe choices toward something riskier.</p><p>Whether or not every experiment lands, the ambition is worth celebrating. In an industry that often rewards repetition, %s chose to evolve, and '%s' is the proof.</p>", $album, $a['name'], $album, $a['name'], $song ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "Rumor: %s preparing a special release, sources say", $a['name'] ),
					'Unconfirmed reports point to an unannounced project later this year.',
					'rumor',
					sprintf( "<p>Unconfirmed reports circulating online suggest %s may be preparing a special release outside the standard '%s' cycle. The claims, which have not been verified by %s, point to a possible digital single or collaboration.</p><p>Representatives have not commented, and fans are urged to treat the reports with caution until an official announcement is made. KpopBlog will update this story if the agency confirms anything.</p>", $a['name'], $album, $a['agency'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "%s dominate weekly music shows with '%s'", $a['name'], $song ),
					'Multiple first-place trophies cap a strong promotion week.',
					'news',
					sprintf( "<p>%s swept this week's music-show lineup, taking first place across several programs with '%s.' The wins cap a busy promotion week for the '%s' era and extend the group's trophy count.</p><p>Acceptance speeches thanked %s for their support, with members visibly emotional during the final broadcast. Encore stages quickly became some of the most-shared clips of the week.</p>", $a['name'], $song, $album, $a['fandom'] ),
				);
			},
			function ( $a, $song, $album ) {
				return array(
					sprintf( "Global round-up: how %s is resonating overseas", $a['name'] ),
					'From sold-out shows to viral clips, the international picture is strong.',
					'global',
					sprintf( "<p>The international footprint of %s continues to expand. Recent data shows strong streaming growth across Southeast Asia, the Americas and Europe, while '%s' has become a fixture on global playlists.</p><p>Local fan communities have organized streaming events, translation projects and charity drives in the group's name. The momentum around '%s' suggests the group's overseas appeal is still accelerating.</p>", $a['name'], $song, $album ),
				);
			},
		);
	}

	private function thread_templates() {
		return array(
			array( 'general', 'What got you into K-pop originally?', 'Curious about everyone\'s origin story. For me it was a late-night YouTube rabbit hole that never ended.' ),
			array( 'general', 'Unpopular opinion thread — keep it civil', 'Drop your most controversial (but respectful) K-pop takes. I\'ll start: B-sides are usually better than title tracks.' ),
			array( 'general', 'How do you organize your photocard collection?', 'Mine has gotten completely out of hand. Do you use binders, top loaders, or just a shoebox of regret?' ),
			array( 'general', 'Best lightstick designs of all time?', 'Some lightsticks are basically art pieces. Which one do you think nailed it?' ),
			array( 'news', 'Discussion: what does the latest Circle data tell us?', 'The new weekly numbers are out. Streaming is up but physical seems to be cooling. Thoughts?' ),
			array( 'news', 'Agency mergers and the future of the industry', 'With so much consolidation among labels, are we heading toward an oligopoly? What does it mean for artists?' ),
			array( 'comebacks', 'This month\'s comeback thread — rate the releases', 'A huge month. Post your ratings and let\'s argue about the best title track.' ),
			array( 'comebacks', 'Which concept change surprised you the most?', 'Some groups completely reinvent themselves each era. Who pulled off the biggest pivot?' ),
			array( 'comebacks', 'Album packaging that deserves an award', 'Some companies really go all out on photobooks and inclusions. Show me your favorites.' ),
			array( 'concerts', 'Concert ticket buying is broken — change my mind', 'Fees, queues, scalpers. How do we fix the live experience for international fans?' ),
			array( 'concerts', 'Post your best concert stories', 'I want to live vicariously. What\'s the most memorable show you\'ve attended?' ),
			array( 'concerts', 'Fan chant appreciation thread', 'There\'s nothing like a full stadium hitting a fan chant in sync. Which one gives you chills?' ),
		);
	}

	private function community_templates() {
		return array(
			'Just finished my first-ever album unboxing and the photobook is gorgeous. The inclusions exceeded expectations.',
			'Started a small streaming guide for newcomers in my local fan group. Happy to share the template if anyone wants it.',
			'Attended a fan meetup this weekend and it was so wholesome. We traded photocards and watched the new MV together.',
			'Hot take: the bridge on the new title track is the best part and I will die on this hill.',
			'Made a birthday cup-sleeve event poster for my bias. First time designing one, be gentle.',
			'The choreography practice video just dropped and the synchronization is unreal. Rewatched it ten times.',
			'Can we appreciate how much the vocals have improved this era? The live stages are so stable.',
			'Finally completed my lightstick shelf. It only took three years and way too much money.',
			'Wrote a long thread analyzing the lore connections in the new teaser. The storytelling is getting so layered.',
			'Anyone else emotional about the group\'s anniversary? Time really flies when you\'re having fun.',
			'Tried to learn the point choreography and my body has filed a formal complaint.',
			'The B-side I was sleeping on turned out to be the best track on the album. Lesson learned.',
			'Organizing a charity donation drive in the fandom\'s name for the upcoming anniversary.',
			'Just got back from the pop-up store. My wallet is empty but my heart is full.',
			'The vocal line harmonies on the new song deserve way more recognition than they get.',
			'Made fan art for the comeback and I\'m nervous to post it. This fandom has been so supportive though.',
		);
	}

	private function comment_templates() {
		return array(
			'Completely agree with this. The growth this era has been incredible to watch.',
			'I was skeptical at first but it\'s grown on me so much after a few listens.',
			'This is the kind of analysis I come to this site for. Well written.',
			'The bridge really is the highlight, no notes.',
			'Adding this to my playlist immediately, thanks for the recommendation.',
			'I saw them live last month and the energy was even better than the recordings.',
			'Interesting take, though I think the previous era had stronger B-sides overall.',
			'The choreography deserves so much more credit than it gets.',
			'As a longtime fan, seeing this level of success still feels surreal.',
			'Can we talk about the production quality though? Absolutely top tier.',
			'I respectfully disagree, but I appreciate the thoughtful write-up.',
			'This made me go back and re-listen to the whole discography. No regrets.',
			'The vocal stability on the live stages has improved so much.',
			'Saving this thread for the next time someone asks for recommendations.',
			'The concept photos alone were worth the wait.',
			'Great discussion, everyone. This community is the best part of being a fan.',
			'I never expected to get this invested in a group but here we are.',
			'The lyric translation really adds a new layer of meaning to the song.',
			'Counting down the days until the tour comes to my city.',
			'This is why I trust this outlet over the clickbait sites.',
		);
	}

	/* ---------------- insertion helpers ---------------- */

	private function insert_post( $args ) {
		$defaults = array( 'post_status' => 'publish', 'comment_status' => 'open' );
		$id = wp_insert_post( array_merge( $defaults, $args ), true );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private function add_comment( $post_id, $user_id, $ts, $content, $parent = 0 ) {
		$author = get_userdata( $user_id );
		$data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $author ? $author->display_name : 'Fan',
			'comment_author_email' => $author ? $author->user_email : 'fan@thekpopblog.test',
			'comment_author_url'   => '',
			'comment_content'      => $content,
			'comment_type'         => 'comment',
			'comment_parent'       => $parent,
			'user_id'              => $user_id,
			'comment_date'         => $this->ts_to_post_date( $ts ),
			'comment_date_gmt'     => $this->ts_to_post_date( $ts ),
			'comment_approved'     => 1,
		);
		return (int) wp_insert_comment( $data );
	}

	/* ---------------- main sections ---------------- */

	private function seed_users() {
		WP_CLI::log( 'Seeding users...' );
		foreach ( $this->author_data() as $a ) {
			$id = username_exists( $a[0] );
			if ( ! $id ) {
				$id = wp_insert_user( array(
					'user_login'   => $a[0],
					'user_pass'    => 'kpopblog_author_local_only',
					'user_email'   => $a[2],
					'display_name' => $a[1],
					'role'         => 'author',
				) );
			}
			if ( ! is_wp_error( $id ) ) {
				update_user_meta( $id, 'kb_role', 'editor' );
				update_user_meta( $id, 'kb_bio', $a[3] );
				update_user_meta( $id, 'kb_country', $a[4] );
				update_user_meta( $id, 'kb_language', 'en' );
				update_user_meta( $id, 'kb_trust_level', $this->rint( 3, 5 ) );
				update_user_meta( $id, 'kb_points', $this->rint( 2000, 12000 ) );
				update_user_meta( $id, 'kb_badges', array( 'verified-writer', 'early-adopter' ) );
				$this->authors[ $a[0] ] = (int) $id;
			}
		}
		$author_ids = array_values( $this->authors );

		foreach ( $this->community_user_data() as $u ) {
			$id = username_exists( $u[0] );
			if ( ! $id ) {
				$id = wp_insert_user( array(
					'user_login'   => $u[0],
					'user_pass'    => 'kpopblog_member_local_only',
					'user_email'   => $u[0] . '@fans.thekpopblog.test',
					'display_name' => $u[0],
					'role'         => 'subscriber',
				) );
			}
			if ( ! is_wp_error( $id ) ) {
				// Spread registration across the three-year window.
				$registered = gmdate( 'Y-m-d H:i:s', $this->random_ts() );
				global $wpdb;
				$wpdb->update( $wpdb->users, array( 'user_registered' => $registered ), array( 'ID' => $id ) );
				clean_user_cache( $id );
				update_user_meta( $id, 'kb_role', 'member' );
				update_user_meta( $id, 'kb_bio', $u[1] );
				update_user_meta( $id, 'kb_country', $u[2] );
				update_user_meta( $id, 'kb_language', 'en' );
				update_user_meta( $id, 'kb_trust_level', $this->rint( 1, 4 ) );
				update_user_meta( $id, 'kb_points', $this->rint( 50, 6000 ) );
				$badges = array();
				if ( $this->rint( 0, 1 ) ) { $badges[] = 'early-adopter'; }
				if ( $this->rint( 0, 2 ) === 0 ) { $badges[] = 'top-contributor'; }
				if ( $this->rint( 0, 3 ) === 0 ) { $badges[] = 'helpful'; }
				update_user_meta( $id, 'kb_badges', $badges );
				$this->community_users[] = (int) $id;
			}
		}
		WP_CLI::log( sprintf( '  %d authors, %d community users.', count( $author_ids ), count( $this->community_users ) ) );
	}

	private function seed_artists() {
		WP_CLI::log( 'Seeding artists + images...' );
		foreach ( $this->artist_data() as $row ) {
			list( $slug, $name, $korean, $type, $agency, $debut, $gen, $fandom, $c1, $c2, $followers, $bio, $songs, $albums ) = $row;
			$img_id = $this->make_image( $slug, $c1, $c2, $name );
			$this->artist_images[ $slug ] = $img_id;

			$existing = get_posts( array( 'post_type' => 'kb_artist', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any' ) );
			$id = $existing ? $existing[0]->ID : $this->insert_post( array(
				'post_type'    => 'kb_artist',
				'post_title'   => $name,
				'post_name'    => $slug,
				'post_content' => $bio,
				'post_excerpt' => $bio,
			) );
			if ( ! $id ) { continue; }
			if ( $img_id ) { set_post_thumbnail( $id, $img_id ); }
			update_post_meta( $id, 'kb_korean_name', $korean );
			update_post_meta( $id, 'kb_type', $type );
			update_post_meta( $id, 'kb_agency', $agency );
			update_post_meta( $id, 'kb_debut_date', $debut );
			update_post_meta( $id, 'kb_fandom_name', $fandom );
			update_post_meta( $id, 'kb_generation', $gen );
			update_post_meta( $id, 'kb_status', 'active' );
			update_post_meta( $id, 'kb_nationality', 'South Korea' );
			update_post_meta( $id, 'kb_follower_count', $followers );
			update_post_meta( $id, 'kb_social_links', array(
				'official'  => 'https://' . str_replace( '-', '', $slug ) . '.official.example',
				'youtube'   => 'https://youtube.com/@' . str_replace( '-', '', $slug ),
				'instagram' => 'https://instagram.com/' . str_replace( '-', '', $slug ),
			) );
			$this->artists[ $slug ] = array(
				'id' => $id, 'slug' => $slug, 'name' => $name, 'agency' => $agency,
				'fandom' => $fandom, 'type' => $type, 'songs' => $songs, 'albums' => $albums,
			);
		}
		WP_CLI::log( sprintf( '  %d artists.', count( $this->artists ) ) );
	}

	private function seed_members() {
		WP_CLI::log( 'Seeding members...' );
		$count = 0;
		foreach ( $this->member_data() as $group_slug => $members ) {
			if ( ! isset( $this->artists[ $group_slug ] ) ) { continue; }
			foreach ( $members as $m ) {
				list( $stage, $full, $korean, $birthday, $positions, $mbti ) = $m;
				$slug = sanitize_title( $group_slug . '-' . $stage );
				$existing = get_posts( array( 'post_type' => 'kb_member', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any' ) );
				$id = $existing ? $existing[0]->ID : $this->insert_post( array(
					'post_type'    => 'kb_member',
					'post_title'   => $stage,
					'post_name'    => $slug,
					'post_content' => sprintf( '%s is a member of %s.', $stage, $this->artists[ $group_slug ]['name'] ),
				) );
				if ( ! $id ) { continue; }
				if ( ! empty( $this->artist_images[ $group_slug ] ) ) { set_post_thumbnail( $id, $this->artist_images[ $group_slug ] ); }
				update_post_meta( $id, 'kb_stage_name', $stage );
				update_post_meta( $id, 'kb_full_name', $full );
				update_post_meta( $id, 'kb_korean_name', $korean );
				update_post_meta( $id, 'kb_birthday', $birthday );
				update_post_meta( $id, 'kb_nationality', 'South Korea' );
				update_post_meta( $id, 'kb_group_slug', $group_slug );
				update_post_meta( $id, 'kb_positions', $positions );
				update_post_meta( $id, 'kb_mbti', $mbti );
				update_post_meta( $id, 'kb_facts', array(
					sprintf( 'Position: %s.', implode( ', ', $positions ) ),
					sprintf( 'MBTI: %s.', $mbti ),
				) );
				$count++;
			}
		}
		WP_CLI::log( sprintf( '  %d members.', $count ) );
	}

	private function seed_comebacks() {
		WP_CLI::log( 'Seeding comebacks...' );
		$count = 0;
		$types = array( 'album', 'single', 'mini', 'repackage', 'digital' );
		foreach ( $this->artists as $slug => $a ) {
			$n = $this->rint( 2, 4 );
			for ( $i = 0; $i < $n; $i++ ) {
				$album = $this->pick( $a['albums'] );
				$ts = $this->random_ts();
				$release = gmdate( 'Y-m-d\TH:i:s', $ts );
				$title = sprintf( '%s — %s', $a['name'], $album );
				$cb_slug = sanitize_title( $slug . '-' . $album . '-' . gmdate( 'Y', $ts ) );
				$existing = get_posts( array( 'post_type' => 'kb_comeback', 'name' => $cb_slug, 'numberposts' => 1, 'post_status' => 'any' ) );
				$id = $existing ? $existing[0]->ID : $this->insert_post( array(
					'post_type'    => 'kb_comeback',
					'post_title'   => $title,
					'post_name'    => $cb_slug,
					'post_content' => sprintf( '%s release from %s.', ucfirst( $this->pick( $types ) ), $a['name'] ),
					'post_date'    => $this->ts_to_post_date( $ts ),
					'post_date_gmt'=> $this->ts_to_post_date( $ts ),
				) );
				if ( ! $id ) { continue; }
				if ( ! empty( $this->artist_images[ $slug ] ) ) { set_post_thumbnail( $id, $this->artist_images[ $slug ] ); }
				update_post_meta( $id, 'kb_artist_slug', $slug );
				update_post_meta( $id, 'kb_type', $this->pick( $types ) );
				update_post_meta( $id, 'kb_release_at', $release );
				$count++;
			}
		}
		WP_CLI::log( sprintf( '  %d comebacks.', $count ) );
	}

	private function seed_charts() {
		WP_CLI::log( 'Seeding charts...' );
		$slugs = array_keys( $this->artists );
		$count = 0;
		for ( $week = 0; $week < 26; $week++ ) {
			$ts = $this->now_ts - ( $week * 7 * DAY_IN_SECONDS );
			$week_start = gmdate( 'Y-m-d', $ts );
			$title = sprintf( 'Global Top 50 — Week of %s', gmdate( 'M j, Y', $ts ) );
			$chart_slug = 'weekly-global-' . gmdate( 'Y-m-d', $ts );
			$existing = get_posts( array( 'post_type' => 'kb_chart', 'name' => $chart_slug, 'numberposts' => 1, 'post_status' => 'any' ) );
			$id = $existing ? $existing[0]->ID : $this->insert_post( array(
				'post_type'    => 'kb_chart',
				'post_title'   => $title,
				'post_name'    => $chart_slug,
				'post_content' => 'Weekly global streaming and sales ranking.',
				'post_date'    => $this->ts_to_post_date( $ts ),
				'post_date_gmt'=> $this->ts_to_post_date( $ts ),
			) );
			if ( ! $id ) { continue; }
			$entries = array();
			$used = array();
			for ( $rank = 1; $rank <= 10; $rank++ ) {
				do { $s = $this->pick( $slugs ); } while ( in_array( $s, $used, true ) );
				$used[] = $s;
				$prev = $rank + $this->rint( -3, 3 );
				$entries[] = array(
					'rank'         => $rank,
					'artistSlug'   => $s,
					'trackTitle'   => $this->pick( $this->artists[ $s ]['songs'] ),
					'previousRank' => ( $prev >= 1 && $prev <= 10 ) ? $prev : null,
					'weeksOnChart' => $this->rint( 1, 18 ),
				);
			}
			update_post_meta( $id, 'kb_chart_id', 'weekly-global' );
			update_post_meta( $id, 'kb_week_start_date', $week_start );
			update_post_meta( $id, 'kb_entries', $entries );
			$count++;
		}
		WP_CLI::log( sprintf( '  %d charts.', $count ) );
	}

	private function seed_articles() {
		WP_CLI::log( 'Seeding articles...' );
		$templates = $this->article_templates();
		$author_ids = array_values( $this->authors );
		$slugs = array_keys( $this->artists );
		$count = 0;
		$total = 130;
		for ( $i = 0; $i < $total; $i++ ) {
			$slug = $this->pick( $slugs );
			$a = $this->artists[ $slug ];
			$song = $this->pick( $a['songs'] );
			$album = $this->pick( $a['albums'] );
			$tpl = $this->pick( $templates );
			list( $title, $subtitle, $category, $body ) = $tpl( $a, $song, $album );

			$ts = $this->random_ts();
			$author_id = $this->pick( $author_ids );
			$post_slug = sanitize_title( $title ) . '-' . substr( md5( $title . $i ), 0, 5 );

			$id = $this->insert_post( array(
				'post_type'     => 'post',
				'post_title'    => $title,
				'post_name'     => $post_slug,
				'post_content'  => $body,
				'post_excerpt'  => wp_trim_words( wp_strip_all_tags( $body ), 32 ),
				'post_author'   => $author_id,
				'post_date'     => $this->ts_to_post_date( $ts ),
				'post_date_gmt' => $this->ts_to_post_date( $ts ),
			) );
			if ( ! $id ) { continue; }
			if ( ! empty( $this->artist_images[ $slug ] ) ) { set_post_thumbnail( $id, $this->artist_images[ $slug ] ); }

			// Assign a native category + tag so archive/taxonomy pages work too.
			$cat = wp_insert_term( ucfirst( $category ), 'category', array( 'slug' => $category ) );
			$cat_id = is_array( $cat ) ? $cat['term_id'] : ( is_wp_error( $cat ) && isset( $cat->error_data['term_exists'] ) ? $cat->error_data['term_exists'] : 0 );
			if ( $cat_id ) { wp_set_post_terms( $id, array( (int) $cat_id ), 'category' ); }
			wp_set_post_terms( $id, array( $slug ), 'post_tag' );

			update_post_meta( $id, 'kb_subtitle', $subtitle );
			update_post_meta( $id, 'kb_category_slug', $category );
			update_post_meta( $id, 'kb_reading_time', $this->rint( 3, 9 ) );
			update_post_meta( $id, 'kb_related_artist_slugs', array( $slug ) );
			update_post_meta( $id, 'kb_language', 'en' );
			update_post_meta( $id, 'kb_source', 'editorial' );
			update_post_meta( $id, 'kb_view_count', $this->rint( 4000, 650000 ) );
			update_post_meta( $id, 'kb_reaction_count', $this->rint( 200, 22000 ) );

			// Comments — more for older, high-view articles.
			$n_comments = $this->rint( 0, 9 );
			for ( $c = 0; $c < $n_comments; $c++ ) {
				$cts = $this->rint( $ts, $this->now_ts );
				$uid = $this->pick( $this->community_users );
				$this->add_comment( $id, $uid, $cts, $this->pick( $this->comment_templates() ) );
			}
			$count++;
		}
		WP_CLI::log( sprintf( '  %d articles.', $count ) );
	}

	private function seed_threads() {
		WP_CLI::log( 'Seeding forum threads + replies...' );
		$author_ids = array_values( $this->authors );
		$slugs = array_keys( $this->artists );
		$count = 0;
		$replies_total = 0;

		// The fixed topical threads.
		$threads = $this->thread_templates();
		// Plus artist-specific discussion threads.
		foreach ( $this->artists as $slug => $a ) {
			$threads[] = array( 'general', sprintf( '%s appreciation thread', $a['name'] ), sprintf( 'A dedicated space to talk about everything %s. Share your favorite moments, stages and songs.', $a['name'] ) );
			$threads[] = array( 'comebacks', sprintf( '%s — discuss the latest release', $a['name'] ), sprintf( 'The new %s era is here. What do %s think of the title track and the B-sides?', $a['name'], $a['fandom'] ) );
		}

		foreach ( $threads as $t ) {
			list( $cat_slug, $title, $body ) = $t;
			$ts = $this->random_ts();
			$author_id = $this->rint( 0, 3 ) === 0 ? $this->pick( $author_ids ) : $this->pick( $this->community_users );
			$post_slug = sanitize_title( $title ) . '-' . substr( md5( $title . $count ), 0, 5 );

			$id = $this->insert_post( array(
				'post_type'     => 'kb_thread',
				'post_title'    => $title,
				'post_name'     => $post_slug,
				'post_content'  => $body,
				'post_author'   => $author_id,
				'post_date'     => $this->ts_to_post_date( $ts ),
				'post_date_gmt' => $this->ts_to_post_date( $ts ),
			) );
			if ( ! $id ) { continue; }

			$term = get_term_by( 'slug', $cat_slug, 'kb_forum_category' );
			if ( $term ) { wp_set_post_terms( $id, array( (int) $term->term_id ), 'kb_forum_category' ); }

			update_post_meta( $id, 'kb_category_slug', $cat_slug );
			update_post_meta( $id, 'kb_language', 'en' );
			update_post_meta( $id, 'kb_views', $this->rint( 300, 48000 ) );
			update_post_meta( $id, 'kb_reactions', $this->rint( 5, 1200 ) );
			update_post_meta( $id, 'kb_rumor', $this->rint( 0, 9 ) === 0 );
			update_post_meta( $id, 'kb_pinned', $this->rint( 0, 14 ) === 0 );
			update_post_meta( $id, 'kb_locked', false );
			update_post_meta( $id, 'kb_official', $this->rint( 0, 12 ) === 0 );
			if ( $this->rint( 0, 1 ) === 0 ) {
				update_post_meta( $id, 'kb_related_artist_slugs', array( $this->pick( $slugs ) ) );
			}

			$n_replies = $this->rint( 0, 12 );
			$last = $ts;
			for ( $r = 0; $r < $n_replies; $r++ ) {
				$cts = $this->rint( $ts, $this->now_ts );
				if ( $cts < $last ) { $cts = $last; }
				$last = $cts;
				$uid = $this->pick( $this->community_users );
				$this->add_comment( $id, $uid, $cts, $this->pick( $this->comment_templates() ) );
				$replies_total++;
			}
			// Bump modified time to the latest reply so "last activity" sorts sensibly.
			if ( $last > $ts ) {
				wp_update_post( array( 'ID' => $id, 'post_modified' => $this->ts_to_post_date( $last ), 'post_modified_gmt' => $this->ts_to_post_date( $last ) ) );
			}
			$count++;
		}
		WP_CLI::log( sprintf( '  %d threads, %d replies.', $count, $replies_total ) );
	}

	private function seed_community() {
		WP_CLI::log( 'Seeding community posts + replies...' );
		$templates = $this->community_templates();
		$slugs = array_keys( $this->artists );
		$count = 0;
		$replies_total = 0;
		$total = 90;
		for ( $i = 0; $i < $total; $i++ ) {
			$ts = $this->random_ts();
			$author_id = $this->pick( $this->community_users );
			$body = $this->pick( $templates );
			$title = wp_trim_words( $body, 6, '…' );

			$id = $this->insert_post( array(
				'post_type'     => 'kb_community',
				'post_title'    => $title,
				'post_name'     => 'community-' . substr( md5( $body . $i ), 0, 8 ),
				'post_content'  => $body,
				'post_author'   => $author_id,
				'post_date'     => $this->ts_to_post_date( $ts ),
				'post_date_gmt' => $this->ts_to_post_date( $ts ),
			) );
			if ( ! $id ) { continue; }
			update_post_meta( $id, 'kb_language', 'en' );
			if ( $this->rint( 0, 1 ) === 0 ) {
				update_post_meta( $id, 'kb_artist_slug', $this->pick( $slugs ) );
			}
			$n_replies = $this->rint( 0, 7 );
			for ( $r = 0; $r < $n_replies; $r++ ) {
				$cts = $this->rint( $ts, $this->now_ts );
				$uid = $this->pick( $this->community_users );
				$this->add_comment( $id, $uid, $cts, $this->pick( $this->comment_templates() ) );
				$replies_total++;
			}
			$count++;
		}
		WP_CLI::log( sprintf( '  %d community posts, %d replies.', $count, $replies_total ) );
	}

	private function seed_polls() {
		WP_CLI::log( 'Seeding polls...' );
		$slugs = array_keys( $this->artists );
		$prompts = array(
			'Which title track defined the year?',
			'Best vocal performance of the season?',
			'Pick the standout B-side.',
			'Which concept was the strongest?',
			'Most anticipated upcoming comeback?',
			'Best music video of the month?',
			'Which choreography was the most impressive?',
			'Your song of the summer?',
		);
		$count = 0;
		for ( $i = 0; $i < 18; $i++ ) {
			$slug = $this->pick( $slugs );
			$a = $this->artists[ $slug ];
			$ts = $this->random_ts();
			$prompt = $this->pick( $prompts );
			$title = sprintf( '%s — %s', $a['name'], $prompt );
			$poll_slug = sanitize_title( $slug . '-poll-' . $i );

			$existing = get_posts( array( 'post_type' => 'kb_poll', 'name' => $poll_slug, 'numberposts' => 1, 'post_status' => 'any' ) );
			$id = $existing ? $existing[0]->ID : $this->insert_post( array(
				'post_type'     => 'kb_poll',
				'post_title'    => $title,
				'post_name'     => $poll_slug,
				'post_content'  => $prompt,
				'post_date'     => $this->ts_to_post_date( $ts ),
				'post_date_gmt' => $this->ts_to_post_date( $ts ),
			) );
			if ( ! $id ) { continue; }

			$used = array();
			$options = array();
			$n_opts = $this->rint( 3, 4 );
			for ( $o = 0; $o < $n_opts; $o++ ) {
				do { $s = $this->pick( $a['songs'] ); } while ( in_array( $s, $used, true ) && count( $used ) < count( $a['songs'] ) );
				$used[] = $s;
				$options[] = array(
					'id'    => 'opt_' . ( $o + 1 ),
					'label' => $s,
					'votes' => $this->rint( 120, 24000 ),
				);
			}
			update_post_meta( $id, 'kb_options', $options );
			update_post_meta( $id, 'kb_artist_slug', $slug );
			update_post_meta( $id, 'kb_ends_at', gmdate( 'Y-m-d\TH:i:s', $this->rint( $this->now_ts, $this->now_ts + 90 * DAY_IN_SECONDS ) ) );
			$count++;
		}
		WP_CLI::log( sprintf( '  %d polls.', $count ) );
	}

	private function seed_videos() {
		WP_CLI::log( 'Seeding videos...' );
		$categories = array( 'Music Video', 'Performance', 'Lyric Video', 'Teaser', 'Behind The Scenes' );
		$count = 0;
		foreach ( $this->artists as $slug => $a ) {
			$n = $this->rint( 1, 2 );
			for ( $i = 0; $i < $n; $i++ ) {
				$song = $this->pick( $a['songs'] );
				$ts = $this->random_ts();
				$title = sprintf( '%s — %s (Official)', $a['name'], $song );
				$v_slug = sanitize_title( $slug . '-' . $song . '-video-' . $i );
				$existing = get_posts( array( 'post_type' => 'kb_video', 'name' => $v_slug, 'numberposts' => 1, 'post_status' => 'any' ) );
				$id = $existing ? $existing[0]->ID : $this->insert_post( array(
					'post_type'     => 'kb_video',
					'post_title'    => $title,
					'post_name'     => $v_slug,
					'post_content'  => sprintf( 'Official video for "%s" by %s.', $song, $a['name'] ),
					'post_date'     => $this->ts_to_post_date( $ts ),
					'post_date_gmt' => $this->ts_to_post_date( $ts ),
				) );
				if ( ! $id ) { continue; }
				if ( ! empty( $this->artist_images[ $slug ] ) ) { set_post_thumbnail( $id, $this->artist_images[ $slug ] ); }
				update_post_meta( $id, 'kb_youtube_id', '' );
				update_post_meta( $id, 'kb_duration', sprintf( '%d:%02d', $this->rint( 2, 4 ), $this->rint( 0, 59 ) ) );
				update_post_meta( $id, 'kb_artist_slug', $slug );
				update_post_meta( $id, 'kb_video_category', $this->pick( $categories ) );
				$count++;
			}
		}
		WP_CLI::log( sprintf( '  %d videos.', $count ) );
	}

	/* ---------------- orchestration ---------------- */

	public function wipe() {
		WP_CLI::log( 'Wiping previously seeded content...' );
		$types = array( 'post', 'kb_artist', 'kb_member', 'kb_comeback', 'kb_chart', 'kb_video', 'kb_thread', 'kb_community', 'kb_poll' );
		foreach ( $types as $type ) {
			$ids = get_posts( array( 'post_type' => $type, 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
			foreach ( $ids as $pid ) { wp_delete_post( $pid, true ); }
		}
		// Comments tied to deleted posts are removed with them; clear strays.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_post_ID NOT IN (SELECT ID FROM {$wpdb->posts})" );
		// Seeded users (authors + members we created), never the bootstrap admin.
		$users = get_users( array( 'fields' => 'ID' ) );
		foreach ( $users as $uid ) {
			$u = get_userdata( $uid );
			if ( ! $u ) { continue; }
			if ( in_array( $u->user_login, array( 'admin', 'member' ), true ) ) { continue; }
			if ( get_user_meta( $uid, 'kb_role', true ) !== '' ) {
				if ( function_exists( 'wp_delete_user' ) || true ) {
					if ( ! function_exists( 'wp_delete_user' ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
					wp_delete_user( $uid );
				}
			}
		}
		delete_option( 'kpopblog_seed_version' );
		WP_CLI::log( '  Wipe complete.' );
	}

	public function run( $fresh ) {
		if ( get_option( 'kpopblog_seed_version' ) && ! $fresh ) {
			WP_CLI::warning( 'Already seeded (option kpopblog_seed_version is set). Use --fresh to rebuild.' );
			return;
		}
		if ( $fresh ) { $this->wipe(); }

		$started = microtime( true );
		$this->seed_users();
		$this->seed_artists();
		$this->seed_members();
		$this->seed_comebacks();
		$this->seed_charts();
		$this->seed_videos();
		$this->seed_articles();
		$this->seed_threads();
		$this->seed_community();
		$this->seed_polls();

		update_option( 'kpopblog_seed_version', '1' );
		// Flip the runtime-data flag so the plugin stops warning about demo data.
		update_option( 'kpopblog_runtime_data_ready', 1 );

		WP_CLI::success( sprintf( 'Seed complete in %.1fs.', microtime( true ) - $started ) );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'kpopblog seed', function ( $args, $assoc ) {
		$seeder = new KpopBlog_Seeder();
		$seeder->run( ! empty( $assoc['fresh'] ) );
	} );
}
