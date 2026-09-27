<?php
/**
 * Starter artist catalogue, artist keyword matching, and image fallbacks.
 *
 * The catalogue gives the news collector a set of artists to follow. Each
 * artist stores its own search keywords and Soompi tag so editors can add or
 * tune artists from wp-admin without touching code.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_ARTIST_MATCHERS_TRANSIENT = 'kpopblog_artist_matchers_v1';

/**
 * Starter catalogue. Keys: name, korean, type, agency, debut, generation,
 * fandom, bio, image (bundled file in media/artists), tag (Soompi tag slug),
 * terms (extra match keywords; short or ambiguous words are case-sensitive).
 */
function kpopblog_artist_catalog() {
	return array(
		'bts' => array( 'name' => 'BTS', 'korean' => '방탄소년단', 'type' => 'boy_group', 'agency' => 'BIGHIT MUSIC', 'debut' => '2013-06-13', 'generation' => 3, 'fandom' => 'ARMY', 'image' => 'bts.jpg', 'tag' => 'bts',
			'bio' => 'Seven-member BIGHIT MUSIC group and the first K-pop act to top the Billboard 200.',
			'terms' => array( 'Bangtan', 'Jungkook', 'Jimin', 'j-hope', 'J-Hope', 'SUGA', 'Suga', 'Kim Taehyung', 'Kim Seokjin' ) ),
		'blackpink' => array( 'name' => 'BLACKPINK', 'korean' => '블랙핑크', 'type' => 'girl_group', 'agency' => 'YG Entertainment', 'debut' => '2016-08-08', 'generation' => 3, 'fandom' => 'BLINK', 'image' => 'blackpink.jpg', 'tag' => 'blackpink',
			'bio' => 'Four-member YG Entertainment group and the first K-pop girl group to headline Coachella.',
			'terms' => array( 'Jennie', 'JENNIE', 'Jisoo', 'JISOO', 'Rosé', 'ROSÉ', 'Lisa', 'LISA', 'Lalisa' ) ),
		'newjeans' => array( 'name' => 'NewJeans', 'korean' => '뉴진스', 'type' => 'girl_group', 'agency' => 'ADOR', 'debut' => '2022-07-22', 'generation' => 4, 'fandom' => 'Bunnies', 'image' => 'newjeans.jpg', 'tag' => 'newjeans',
			'bio' => 'Five-member ADOR group known for "Attention," "Hype Boy," and "Ditto."',
			'terms' => array( 'NJZ', 'Haerin', 'Hyein' ) ),
		'le-sserafim' => array( 'name' => 'LE SSERAFIM', 'korean' => '르세라핌', 'type' => 'girl_group', 'agency' => 'Source Music', 'debut' => '2022-05-02', 'generation' => 4, 'fandom' => 'FEARNOT', 'image' => 'le-sserafim.jpg', 'tag' => 'le-sserafim',
			'bio' => 'Five-member Source Music group whose name is an anagram of "I\'m fearless."',
			'terms' => array( 'Kazuha', 'Huh Yunjin', 'Hong Eunchae' ) ),
		'aespa' => array( 'name' => 'aespa', 'korean' => '에스파', 'type' => 'girl_group', 'agency' => 'SM Entertainment', 'debut' => '2020-11-17', 'generation' => 4, 'fandom' => 'MY', 'image' => 'aespa.jpg', 'tag' => 'aespa',
			'bio' => 'Four-member SM Entertainment group behind "Next Level," "Spicy," and "Supernova."',
			'terms' => array( 'Karina', 'Ningning', 'Giselle' ) ),
		'ive' => array( 'name' => 'IVE', 'korean' => '아이브', 'type' => 'girl_group', 'agency' => 'Starship Entertainment', 'debut' => '2021-12-01', 'generation' => 4, 'fandom' => 'DIVE', 'image' => 'ive.jpg', 'tag' => 'ive',
			'bio' => 'Six-member Starship Entertainment group that debuted with "ELEVEN."',
			'terms' => array( 'Jang Wonyoung', 'Wonyoung', 'An Yujin', 'Ahn Yujin', 'Leeseo' ) ),
		'stray-kids' => array( 'name' => 'Stray Kids', 'korean' => '스트레이 키즈', 'type' => 'boy_group', 'agency' => 'JYP Entertainment', 'debut' => '2018-03-25', 'generation' => 4, 'fandom' => 'STAY', 'image' => 'stray-kids.jpg', 'tag' => 'stray-kids',
			'bio' => 'Self-producing eight-member JYP Entertainment group with multiple No. 1 albums on the Billboard 200.',
			'terms' => array( '스트레이키즈', 'Bang Chan', 'Hyunjin', 'Changbin', 'Seungmin', 'Lee Know' ) ),
		'twice' => array( 'name' => 'TWICE', 'korean' => '트와이스', 'type' => 'girl_group', 'agency' => 'JYP Entertainment', 'debut' => '2015-10-20', 'generation' => 3, 'fandom' => 'ONCE', 'image' => 'twice.jpg', 'tag' => 'twice',
			'bio' => 'Nine-member JYP Entertainment group behind "Cheer Up," "TT," and "Feel Special."',
			'terms' => array( 'Nayeon', 'Jeongyeon', 'Jihyo', 'Dahyun', 'Tzuyu' ) ),
		'seventeen' => array( 'name' => 'SEVENTEEN', 'korean' => '세븐틴', 'type' => 'boy_group', 'agency' => 'PLEDIS Entertainment', 'debut' => '2015-05-26', 'generation' => 3, 'fandom' => 'CARAT', 'image' => 'seventeen.jpg', 'tag' => 'seventeen',
			'bio' => 'Self-producing PLEDIS Entertainment group organized into vocal, hip-hop, and performance units.',
			'terms' => array( 'S.Coups', 'Jeonghan', 'Wonwoo', 'Woozi', 'Mingyu', 'Seungkwan', 'Vernon', 'Hoshi', 'BSS', 'BooSeokSoon' ) ),
		'itzy' => array( 'name' => 'ITZY', 'korean' => '있지', 'type' => 'girl_group', 'agency' => 'JYP Entertainment', 'debut' => '2019-02-12', 'generation' => 4, 'fandom' => 'MIDZY', 'image' => 'itzy.jpg', 'tag' => 'itzy',
			'bio' => 'Five-member JYP Entertainment group that debuted with "DALLA DALLA."',
			'terms' => array( 'Yeji', 'Ryujin', 'Chaeryeong' ) ),
		'riize' => array( 'name' => 'RIIZE', 'korean' => '라이즈', 'type' => 'boy_group', 'agency' => 'SM Entertainment', 'debut' => '2023-09-04', 'generation' => 5, 'fandom' => 'BRIIZE', 'image' => 'riize.jpg', 'tag' => 'riize',
			'bio' => 'SM Entertainment boy group that debuted with "Get A Guitar."',
			'terms' => array( 'Wonbin', 'Shotaro', 'Sungchan', 'Eunseok' ) ),
		'enhypen' => array( 'name' => 'ENHYPEN', 'korean' => '엔하이픈', 'type' => 'boy_group', 'agency' => 'BELIFT LAB', 'debut' => '2020-11-30', 'generation' => 4, 'fandom' => 'ENGENE', 'image' => 'enhypen.jpg', 'tag' => 'enhypen',
			'bio' => 'Seven-member BELIFT LAB group formed through the survival show "I-LAND."',
			'terms' => array( 'Jungwon', 'Heeseung', 'Sunghoon', 'Sunoo', 'NI-KI', 'Ni-ki' ) ),
		'iu' => array( 'name' => 'IU', 'korean' => '아이유', 'type' => 'soloist', 'agency' => 'EDAM Entertainment', 'debut' => '2008-09-18', 'generation' => 2, 'fandom' => 'UAENA', 'image' => 'iu.jpg', 'tag' => 'iu',
			'bio' => 'Singer-songwriter and actress behind "Good Day," "Palette," and "Love wins all."',
			'terms' => array( 'Lee Ji Eun', 'Lee Ji-eun' ) ),
		'txt' => array( 'name' => 'TOMORROW X TOGETHER', 'korean' => '투모로우바이투게더', 'type' => 'boy_group', 'agency' => 'BIGHIT MUSIC', 'debut' => '2019-03-04', 'generation' => 4, 'fandom' => 'MOA', 'image' => 'txt.jpg', 'tag' => 'txt',
			'bio' => 'Five-member BIGHIT MUSIC group also known as TXT.',
			'terms' => array( 'TXT', 'Yeonjun', 'Soobin', 'Beomgyu', 'Huening Kai' ) ),
		'gidle' => array( 'name' => 'i-dle', 'korean' => '아이들', 'type' => 'girl_group', 'agency' => 'CUBE Entertainment', 'debut' => '2018-05-02', 'generation' => 4, 'fandom' => 'NEVERLAND', 'image' => 'gidle.jpg', 'tag' => 'i-dle',
			'bio' => 'Self-producing CUBE Entertainment girl group formerly known as (G)I-DLE.',
			'terms' => array( '(G)I-DLE', '(G)I-dle', '여자아이들', 'Soyeon', 'Miyeon', 'Minnie', 'Yuqi', 'Shuhua' ) ),
		'zerobaseone' => array( 'name' => 'ZEROBASEONE', 'korean' => '제로베이스원', 'type' => 'boy_group', 'agency' => 'WAKEONE', 'debut' => '2023-07-10', 'generation' => 5, 'fandom' => 'ZEROSE', 'image' => 'zerobaseone.jpg', 'tag' => 'zerobaseone',
			'bio' => 'Boy group formed through the survival show "Boys Planet."',
			'terms' => array( 'ZB1', 'Sung Han Bin', 'Sung Hanbin', 'Zhang Hao', 'Park Gun Wook' ) ),
		'ateez' => array( 'name' => 'ATEEZ', 'korean' => '에이티즈', 'type' => 'boy_group', 'agency' => 'KQ Entertainment', 'debut' => '2018-10-24', 'generation' => 4, 'fandom' => 'ATINY', 'tag' => 'ateez',
			'bio' => 'Eight-member KQ Entertainment boy group known for high-energy performances.',
			'terms' => array( 'Hongjoong', 'Seonghwa', 'Wooyoung', 'Yeosang', 'Jongho' ) ),
		'babymonster' => array( 'name' => 'BABYMONSTER', 'korean' => '베이비몬스터', 'type' => 'girl_group', 'agency' => 'YG Entertainment', 'debut' => '2024-04-01', 'generation' => 5, 'fandom' => 'MONSTIEZ', 'tag' => 'babymonster',
			'bio' => 'YG Entertainment girl group behind "SHEESH" and "DRIP."',
			'terms' => array( 'Ahyeon', 'Pharita', 'Chiquita' ) ),
		'illit' => array( 'name' => 'ILLIT', 'korean' => '아일릿', 'type' => 'girl_group', 'agency' => 'BELIFT LAB', 'debut' => '2024-03-25', 'generation' => 5, 'fandom' => 'GLLIT', 'tag' => 'illit',
			'bio' => 'BELIFT LAB girl group that broke out with "Magnetic."',
			'terms' => array( 'Wonhee', 'Iroha', 'Yunah', 'Moka' ) ),
		'kiss-of-life' => array( 'name' => 'KISS OF LIFE', 'korean' => '키스오브라이프', 'type' => 'girl_group', 'agency' => 'S2 Entertainment', 'debut' => '2023-07-05', 'generation' => 5, 'fandom' => 'KISSY', 'tag' => 'kiss-of-life',
			'bio' => 'S2 Entertainment girl group known for vocal-driven R&B and pop.',
			'terms' => array( 'KIOF' ) ),
		'nmixx' => array( 'name' => 'NMIXX', 'korean' => '엔믹스', 'type' => 'girl_group', 'agency' => 'JYP Entertainment', 'debut' => '2022-02-22', 'generation' => 4, 'fandom' => 'NSWER', 'tag' => 'nmixx',
			'bio' => 'JYP Entertainment girl group known for genre-blending "MIXX POP."',
			'terms' => array( 'Haewon', 'Sullyoon', 'Kyujin' ) ),
		'tws' => array( 'name' => 'TWS', 'korean' => '투어스', 'type' => 'boy_group', 'agency' => 'PLEDIS Entertainment', 'debut' => '2024-01-22', 'generation' => 5, 'fandom' => '42', 'tag' => 'tws',
			'bio' => 'PLEDIS Entertainment boy group behind "plot twist."',
			'terms' => array() ),
		'boynextdoor' => array( 'name' => 'BOYNEXTDOOR', 'korean' => '보이넥스트도어', 'type' => 'boy_group', 'agency' => 'KOZ Entertainment', 'debut' => '2023-05-30', 'generation' => 5, 'fandom' => 'ONEDOOR', 'tag' => 'boynextdoor',
			'bio' => 'KOZ Entertainment boy group known for self-written, everyday storytelling.',
			'terms' => array() ),
		'cortis' => array( 'name' => 'CORTIS', 'korean' => '코르티스', 'type' => 'boy_group', 'agency' => 'BIGHIT MUSIC', 'debut' => '', 'generation' => 5, 'fandom' => '', 'tag' => 'cortis',
			'bio' => 'BIGHIT MUSIC boy group that co-creates its music, choreography, and visuals.',
			'terms' => array() ),
		'nct-127' => array( 'name' => 'NCT 127', 'korean' => '엔시티 127', 'type' => 'boy_group', 'agency' => 'SM Entertainment', 'debut' => '2016-07-07', 'generation' => 3, 'fandom' => 'NCTzen', 'tag' => 'nct-127',
			'bio' => 'Seoul-based SM Entertainment unit of NCT.',
			'terms' => array( 'Taeyong', 'Doyoung', 'Haechan', 'Jungwoo' ) ),
		'nct-dream' => array( 'name' => 'NCT DREAM', 'korean' => '엔시티 드림', 'type' => 'boy_group', 'agency' => 'SM Entertainment', 'debut' => '2016-08-25', 'generation' => 3, 'fandom' => 'NCTzen', 'tag' => 'nct-dream',
			'bio' => 'SM Entertainment unit of NCT behind "Hot Sauce" and "Candy."',
			'terms' => array( 'Jeno', 'Jaemin', 'Renjun', 'Chenle' ) ),
		'exo' => array( 'name' => 'EXO', 'korean' => '엑소', 'type' => 'boy_group', 'agency' => 'SM Entertainment', 'debut' => '2012-04-08', 'generation' => 3, 'fandom' => 'EXO-L', 'tag' => 'exo',
			'bio' => 'SM Entertainment boy group behind "Growl," "Love Shot," and "Monster."',
			'terms' => array( 'Baekhyun', 'Chanyeol', 'Sehun', 'Xiumin', 'Suho' ) ),
		'red-velvet' => array( 'name' => 'Red Velvet', 'korean' => '레드벨벳', 'type' => 'girl_group', 'agency' => 'SM Entertainment', 'debut' => '2014-08-01', 'generation' => 3, 'fandom' => 'ReVeluv', 'tag' => 'red-velvet',
			'bio' => 'SM Entertainment girl group known for its dual "Red" and "Velvet" concepts.',
			'terms' => array( 'Seulgi' ) ),
		'treasure' => array( 'name' => 'TREASURE', 'korean' => '트레저', 'type' => 'boy_group', 'agency' => 'YG Entertainment', 'debut' => '2020-08-07', 'generation' => 4, 'fandom' => 'Teume', 'tag' => 'treasure',
			'bio' => 'YG Entertainment boy group behind "JIKJIN" and "HELLO."',
			'terms' => array() ),
		'katseye' => array( 'name' => 'KATSEYE', 'korean' => '캣츠아이', 'type' => 'girl_group', 'agency' => 'HYBE x Geffen Records', 'debut' => '2024-06-28', 'generation' => 5, 'fandom' => 'EYEKONS', 'tag' => 'katseye',
			'bio' => 'Global girl group created by HYBE and Geffen Records.',
			'terms' => array() ),
	);
}

/**
 * Member rosters for the catalogue: [stage name, full name, Korean name, birthday, positions].
 */
function kpopblog_artist_member_catalog() {
	return array(
		'bts' => array(
			array( 'RM', 'Kim Nam-joon', '김남준', '1994-09-12', array( 'Leader', 'Rapper' ) ),
			array( 'Jin', 'Kim Seok-jin', '김석진', '1992-12-04', array( 'Vocalist' ) ),
			array( 'SUGA', 'Min Yoon-gi', '민윤기', '1993-03-09', array( 'Rapper' ) ),
			array( 'j-hope', 'Jung Ho-seok', '정호석', '1994-02-18', array( 'Rapper', 'Dancer' ) ),
			array( 'Jimin', 'Park Ji-min', '박지민', '1995-10-13', array( 'Vocalist', 'Dancer' ) ),
			array( 'V', 'Kim Tae-hyung', '김태형', '1995-12-30', array( 'Vocalist' ) ),
			array( 'Jungkook', 'Jeon Jung-kook', '전정국', '1997-09-01', array( 'Vocalist', 'Maknae' ) ),
		),
		'blackpink' => array(
			array( 'Jisoo', 'Kim Ji-soo', '김지수', '1995-01-03', array( 'Vocalist' ) ),
			array( 'Jennie', 'Jennie Kim', '김제니', '1996-01-16', array( 'Rapper', 'Vocalist' ) ),
			array( 'Rosé', 'Park Chae-young', '박채영', '1997-02-11', array( 'Main Vocalist' ) ),
			array( 'Lisa', 'Lalisa Manobal', '라리사 마노반', '1997-03-27', array( 'Main Dancer', 'Rapper' ) ),
		),
		'newjeans' => array(
			array( 'Minji', 'Kim Min-ji', '김민지', '2004-05-07', array( 'Vocalist' ) ),
			array( 'Hanni', 'Hanni Pham', '하니 팜', '2004-10-06', array( 'Vocalist' ) ),
			array( 'Danielle', 'Danielle Marsh', '다니엘 마쉬', '2005-04-11', array( 'Vocalist' ) ),
			array( 'Haerin', 'Kang Hae-rin', '강해린', '2006-05-15', array( 'Vocalist' ) ),
			array( 'Hyein', 'Lee Hye-in', '이혜인', '2008-04-21', array( 'Vocalist', 'Maknae' ) ),
		),
		'le-sserafim' => array(
			array( 'Sakura', 'Miyawaki Sakura', '미야와키 사쿠라', '1998-03-19', array( 'Vocalist' ) ),
			array( 'Kim Chaewon', 'Kim Chae-won', '김채원', '2000-08-01', array( 'Leader', 'Vocalist' ) ),
			array( 'Huh Yunjin', 'Huh Yun-jin', '허윤진', '2001-10-08', array( 'Vocalist' ) ),
			array( 'Kazuha', 'Nakamura Kazuha', '나카무라 카즈하', '2003-08-09', array( 'Dancer', 'Vocalist' ) ),
			array( 'Hong Eunchae', 'Hong Eun-chae', '홍은채', '2006-11-10', array( 'Vocalist', 'Maknae' ) ),
		),
		'aespa' => array(
			array( 'Karina', 'Yu Ji-min', '유지민', '2000-04-11', array( 'Leader', 'Dancer' ) ),
			array( 'Giselle', 'Uchinaga Aeri', '우치나가 에리', '2000-10-30', array( 'Rapper' ) ),
			array( 'Winter', 'Kim Min-jeong', '김민정', '2001-01-01', array( 'Main Vocalist' ) ),
			array( 'Ningning', 'Ning Yizhuo', '닝이줘', '2002-10-23', array( 'Main Vocalist', 'Maknae' ) ),
		),
		'ive' => array(
			array( 'An Yujin', 'An Yu-jin', '안유진', '2003-09-01', array( 'Leader', 'Vocalist' ) ),
			array( 'Gaeul', 'Kim Ga-eul', '김가을', '2002-09-24', array( 'Dancer' ) ),
			array( 'Rei', 'Naoi Rei', '나오이 레이', '2004-02-03', array( 'Rapper' ) ),
			array( 'Jang Wonyoung', 'Jang Won-young', '장원영', '2004-08-31', array( 'Vocalist' ) ),
			array( 'Liz', 'Kim Ji-won', '김지원', '2004-11-21', array( 'Main Vocalist' ) ),
			array( 'Leeseo', 'Lee Hyun-seo', '이현서', '2007-02-21', array( 'Vocalist', 'Maknae' ) ),
		),
		'stray-kids' => array(
			array( 'Bang Chan', 'Christopher Bang', '방찬', '1997-10-03', array( 'Leader', 'Producer' ) ),
			array( 'Lee Know', 'Lee Min-ho', '이민호', '1998-10-25', array( 'Dancer', 'Vocalist' ) ),
			array( 'Changbin', 'Seo Chang-bin', '서창빈', '1999-08-11', array( 'Rapper', 'Producer' ) ),
			array( 'Hyunjin', 'Hwang Hyun-jin', '황현진', '2000-03-20', array( 'Dancer', 'Rapper' ) ),
			array( 'Han', 'Han Ji-sung', '한지성', '2000-09-14', array( 'Rapper', 'Producer' ) ),
			array( 'Felix', 'Lee Felix', '이용복', '2000-09-15', array( 'Dancer', 'Rapper' ) ),
			array( 'Seungmin', 'Kim Seung-min', '김승민', '2000-09-22', array( 'Vocalist' ) ),
			array( 'I.N', 'Yang Jeong-in', '양정인', '2001-02-08', array( 'Vocalist', 'Maknae' ) ),
		),
		'twice' => array(
			array( 'Nayeon', 'Im Na-yeon', '임나연', '1995-09-22', array( 'Vocalist' ) ),
			array( 'Jeongyeon', 'Yoo Jeong-yeon', '유정연', '1996-11-01', array( 'Vocalist' ) ),
			array( 'Momo', 'Hirai Momo', '히라이 모모', '1996-11-09', array( 'Dancer' ) ),
			array( 'Sana', 'Minatozaki Sana', '미나토자키 사나', '1996-12-29', array( 'Vocalist' ) ),
			array( 'Jihyo', 'Park Ji-hyo', '박지효', '1997-02-01', array( 'Leader', 'Vocalist' ) ),
			array( 'Mina', 'Myoui Mina', '묘이 미나', '1997-03-24', array( 'Dancer' ) ),
			array( 'Dahyun', 'Kim Da-hyun', '김다현', '1998-05-28', array( 'Rapper' ) ),
			array( 'Chaeyoung', 'Son Chae-young', '손채영', '1999-04-23', array( 'Rapper' ) ),
			array( 'Tzuyu', 'Chou Tzu-yu', '저우쯔위', '1999-06-14', array( 'Vocalist', 'Maknae' ) ),
		),
		'seventeen' => array(
			array( 'S.Coups', 'Choi Seung-cheol', '최승철', '1995-08-08', array( 'Leader', 'Rapper' ) ),
			array( 'Jeonghan', 'Yoon Jeong-han', '윤정한', '1995-10-04', array( 'Vocalist' ) ),
			array( 'Joshua', 'Hong Ji-soo', '홍지수', '1995-12-30', array( 'Vocalist' ) ),
			array( 'Jun', 'Wen Junhui', '문준휘', '1996-06-10', array( 'Dancer' ) ),
			array( 'Hoshi', 'Kwon Soon-young', '권순영', '1996-06-15', array( 'Performance Leader' ) ),
			array( 'Wonwoo', 'Jeon Won-woo', '전원우', '1996-07-17', array( 'Rapper' ) ),
			array( 'Woozi', 'Lee Ji-hoon', '이지훈', '1996-11-22', array( 'Vocal Leader', 'Producer' ) ),
			array( 'DK', 'Lee Seok-min', '이석민', '1997-02-18', array( 'Vocalist' ) ),
			array( 'Mingyu', 'Kim Min-gyu', '김민규', '1997-04-06', array( 'Rapper' ) ),
			array( 'The8', 'Xu Minghao', '서명호', '1997-11-07', array( 'Dancer' ) ),
			array( 'Seungkwan', 'Boo Seung-kwan', '부승관', '1998-01-16', array( 'Vocalist' ) ),
			array( 'Vernon', 'Hansol Vernon Chwe', '최한솔', '1998-02-18', array( 'Rapper' ) ),
			array( 'Dino', 'Lee Chan', '이찬', '1999-02-11', array( 'Dancer', 'Maknae' ) ),
		),
		'itzy' => array(
			array( 'Yeji', 'Hwang Ye-ji', '황예지', '2000-05-26', array( 'Leader', 'Dancer' ) ),
			array( 'Lia', 'Choi Ji-su', '최지수', '2000-07-21', array( 'Vocalist' ) ),
			array( 'Ryujin', 'Shin Ryu-jin', '신류진', '2001-04-17', array( 'Rapper', 'Dancer' ) ),
			array( 'Chaeryeong', 'Lee Chae-ryeong', '이채령', '2001-06-05', array( 'Dancer' ) ),
			array( 'Yuna', 'Shin Yu-na', '신유나', '2003-12-09', array( 'Vocalist', 'Maknae' ) ),
		),
		'enhypen' => array(
			array( 'Jungwon', 'Yang Jung-won', '양정원', '2004-02-09', array( 'Leader', 'Vocalist' ) ),
			array( 'Heeseung', 'Lee Hee-seung', '이희승', '2001-10-15', array( 'Vocalist' ) ),
			array( 'Jay', 'Park Jong-seong', '박종성', '2002-04-20', array( 'Vocalist' ) ),
			array( 'Jake', 'Jake Sim', '심재윤', '2002-11-15', array( 'Vocalist' ) ),
			array( 'Sunghoon', 'Park Sung-hoon', '박성훈', '2002-12-08', array( 'Vocalist', 'Dancer' ) ),
			array( 'Sunoo', 'Kim Sun-oo', '김선우', '2003-06-24', array( 'Vocalist' ) ),
			array( 'Ni-ki', 'Nishimura Riki', '니시무라 리키', '2005-12-09', array( 'Dancer', 'Maknae' ) ),
		),
		'txt' => array(
			array( 'Soobin', 'Choi Soo-bin', '최수빈', '2000-12-05', array( 'Leader', 'Vocalist' ) ),
			array( 'Yeonjun', 'Choi Yeon-jun', '최연준', '1999-09-13', array( 'Rapper', 'Dancer' ) ),
			array( 'Beomgyu', 'Choi Beom-gyu', '최범규', '2001-03-13', array( 'Vocalist' ) ),
			array( 'Taehyun', 'Kang Tae-hyun', '강태현', '2002-02-05', array( 'Vocalist' ) ),
			array( 'Huening Kai', 'Kai Kamal Huening', '휴닝카이', '2002-08-14', array( 'Vocalist', 'Maknae' ) ),
		),
		'gidle' => array(
			array( 'Miyeon', 'Cho Mi-yeon', '조미연', '1997-01-31', array( 'Vocalist' ) ),
			array( 'Minnie', 'Nicha Yontararak', '니차 욘따라락', '1997-10-23', array( 'Vocalist' ) ),
			array( 'Soyeon', 'Jeon So-yeon', '전소연', '1998-08-26', array( 'Leader', 'Rapper', 'Producer' ) ),
			array( 'Yuqi', 'Song Yu-qi', '송우기', '1999-09-23', array( 'Vocalist' ) ),
			array( 'Shuhua', 'Yeh Shu-hua', '예슈화', '2000-01-06', array( 'Vocalist', 'Maknae' ) ),
		),
		'riize' => array(
			array( 'Shotaro', 'Osaki Shotaro', '오사키 쇼타로', '2000-11-25', array( 'Dancer' ) ),
			array( 'Eunseok', 'Song Eun-seok', '송은석', '2001-03-19', array( 'Vocalist' ) ),
			array( 'Sungchan', 'Jung Sung-chan', '정성찬', '2001-09-13', array( 'Rapper' ) ),
			array( 'Wonbin', 'Park Won-bin', '박원빈', '2002-03-02', array( 'Vocalist' ) ),
			array( 'Sohee', 'Lee So-hee', '이소희', '2003-11-21', array( 'Vocalist' ) ),
			array( 'Anton', 'Anton Lee', '이찬영', '2004-03-21', array( 'Vocalist', 'Maknae' ) ),
		),
	);
}

function kpopblog_register_artist_catalog_meta() {
	$string = array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' );
	register_post_meta( 'kb_artist', 'kb_search_terms', $string );
	register_post_meta( 'kb_artist', 'kb_news_tag', $string );
	register_post_meta( 'kb_artist', 'kb_bundled_image', $string );
	register_post_meta( 'kb_artist', 'kb_catalog_key', $string );
}
add_action( 'init', 'kpopblog_register_artist_catalog_meta' );

/** Public URL for the generic news artwork shipped with the plugin. */
function kpopblog_placeholder_image_url() {
	return KPOPBLOG_URL . 'media/placeholder-news.svg';
}

/** Artist image: featured image, then bundled catalogue artwork, then the generic artwork. */
function kpopblog_artist_image_url( $artist_id ) {
	$image = kpopblog_thumb_url( $artist_id );
	if ( '' !== $image ) { return $image; }
	$bundled = sanitize_file_name( (string) get_post_meta( $artist_id, 'kb_bundled_image', true ) );
	if ( '' !== $bundled && is_readable( KPOPBLOG_PATH . 'media/artists/' . $bundled ) ) {
		return KPOPBLOG_URL . 'media/artists/' . $bundled;
	}
	return kpopblog_placeholder_image_url();
}

function kpopblog_get_artist_by_slug( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) { return null; }
	$posts = get_posts( array( 'post_type' => 'kb_artist', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'publish' ) );
	return $posts ? $posts[0] : null;
}

/**
 * Create missing catalogue artists and members. Existing artists keep every
 * editor change; only empty automation fields are filled in.
 *
 * @return array{artists:int,members:int}
 */
function kpopblog_seed_artist_catalog() {
	$created = array( 'artists' => 0, 'members' => 0 );
	$members = kpopblog_artist_member_catalog();

	foreach ( kpopblog_artist_catalog() as $slug => $artist ) {
		$existing = get_posts( array( 'post_type' => 'kb_artist', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any' ) );
		if ( $existing ) {
			$post_id = (int) $existing[0]->ID;
		} else {
			$post_id = wp_insert_post( wp_slash( array(
				'post_type'    => 'kb_artist',
				'post_status'  => 'publish',
				'post_title'   => $artist['name'],
				'post_name'    => $slug,
				'post_content' => $artist['bio'],
				'post_excerpt' => $artist['bio'],
			) ), true );
			if ( is_wp_error( $post_id ) ) { continue; }
			$created['artists']++;
			$fields = array(
				'kb_korean_name' => $artist['korean'],
				'kb_type'        => $artist['type'],
				'kb_agency'      => $artist['agency'],
				'kb_debut_date'  => $artist['debut'],
				'kb_fandom_name' => $artist['fandom'],
				'kb_status'      => 'active',
				'kb_nationality' => 'katseye' === $slug ? 'International' : 'South Korea',
				'kb_catalog_key' => $slug,
			);
			foreach ( $fields as $key => $value ) {
				if ( '' !== $value ) { update_post_meta( $post_id, $key, $value ); }
			}
			update_post_meta( $post_id, 'kb_generation', (int) $artist['generation'] );
		}

		if ( '' === (string) get_post_meta( $post_id, 'kb_search_terms', true ) ) {
			$terms = array_merge( array( $artist['name'], $artist['korean'] ), $artist['terms'] );
			update_post_meta( $post_id, 'kb_search_terms', implode( ', ', array_unique( array_filter( $terms ) ) ) );
		}
		if ( '' === (string) get_post_meta( $post_id, 'kb_news_tag', true ) && ! empty( $artist['tag'] ) ) {
			update_post_meta( $post_id, 'kb_news_tag', $artist['tag'] );
		}
		if ( ! empty( $artist['image'] ) && '' === (string) get_post_meta( $post_id, 'kb_bundled_image', true ) ) {
			update_post_meta( $post_id, 'kb_bundled_image', $artist['image'] );
		}

		if ( empty( $members[ $slug ] ) ) { continue; }
		$has_members = get_posts( array(
			'post_type'   => 'kb_member',
			'numberposts' => 1,
			'fields'      => 'ids',
			'post_status' => 'any',
			'meta_key'    => 'kb_group_slug',
			'meta_value'  => $slug,
		) );
		if ( $has_members ) { continue; }
		foreach ( $members[ $slug ] as $member ) {
			list( $stage, $full, $korean, $birthday, $positions ) = $member;
			$member_id = wp_insert_post( wp_slash( array(
				'post_type'   => 'kb_member',
				'post_status' => 'publish',
				'post_title'  => $stage,
				'post_name'   => sanitize_title( $slug . '-' . $stage ),
			) ), true );
			if ( is_wp_error( $member_id ) ) { continue; }
			$created['members']++;
			update_post_meta( $member_id, 'kb_stage_name', $stage );
			update_post_meta( $member_id, 'kb_full_name', $full );
			update_post_meta( $member_id, 'kb_korean_name', $korean );
			update_post_meta( $member_id, 'kb_birthday', $birthday );
			update_post_meta( $member_id, 'kb_group_slug', $slug );
			update_post_meta( $member_id, 'kb_positions', $positions );
		}
	}

	delete_transient( KPOPBLOG_ARTIST_MATCHERS_TRANSIENT );
	return $created;
}

/**
 * Compile published artists into keyword matchers.
 *
 * @return array<int,array{slug:string,name:string,tag:string,patterns:array,exact:array}>
 */
function kpopblog_get_artist_matchers() {
	$cached = get_transient( KPOPBLOG_ARTIST_MATCHERS_TRANSIENT );
	if ( is_array( $cached ) ) { return $cached; }

	$matchers = array();
	$artists  = get_posts( array( 'post_type' => 'kb_artist', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	foreach ( $artists as $artist ) {
		$name  = kpopblog_decode_text_entities( get_the_title( $artist ) );
		$terms = array_merge(
			array( $name, (string) get_post_meta( $artist->ID, 'kb_korean_name', true ) ),
			array_map( 'trim', explode( ',', (string) get_post_meta( $artist->ID, 'kb_search_terms', true ) ) )
		);
		$patterns = array();
		$exact    = array();
		foreach ( array_unique( array_filter( $terms, 'strlen' ) ) as $term ) {
			$exact[] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
			if ( preg_match( '/[^\x00-\x7F]/', $term ) && ! preg_match( '/[A-Za-z]/', $term ) ) {
				// Hangul and other non-Latin names match as plain substrings.
				$patterns[] = '/' . preg_quote( $term, '/' ) . '/u';
				continue;
			}
			// Short or all-caps names ("IVE", "IU", "EXO") must match exactly to avoid common words.
			$case_sensitive = strlen( $term ) <= 4 || strtoupper( $term ) === $term;
			$patterns[] = '/(?<![\p{L}\p{N}])' . preg_quote( $term, '/' ) . '(?![\p{L}\p{N}])/u' . ( $case_sensitive ? '' : 'i' );
		}
		$matchers[] = array(
			'slug'     => $artist->post_name,
			'name'     => $name,
			'tag'      => sanitize_title( (string) get_post_meta( $artist->ID, 'kb_news_tag', true ) ),
			'patterns' => $patterns,
			'exact'    => array_values( array_unique( $exact ) ),
		);
	}
	set_transient( KPOPBLOG_ARTIST_MATCHERS_TRANSIENT, $matchers, 6 * HOUR_IN_SECONDS );
	return $matchers;
}

function kpopblog_flush_artist_matchers( $post_id = 0 ) {
	if ( $post_id && 'kb_artist' !== get_post_type( $post_id ) ) { return; }
	delete_transient( KPOPBLOG_ARTIST_MATCHERS_TRANSIENT );
}
add_action( 'save_post_kb_artist', 'kpopblog_flush_artist_matchers' );
add_action( 'deleted_post', 'kpopblog_flush_artist_matchers' );
add_action( 'trashed_post', 'kpopblog_flush_artist_matchers' );

/**
 * Find artists mentioned in text or listed in feed categories.
 * Title mentions rank first so the primary artist is the headline subject.
 *
 * @return string[] Artist slugs.
 */
function kpopblog_match_artists( $title, $body = '', array $categories = array() ) {
	$title_hits = array();
	$other_hits = array();
	$categories = array_map( function ( $category ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $category ), 'UTF-8' ) : strtolower( trim( (string) $category ) );
	}, $categories );

	foreach ( kpopblog_get_artist_matchers() as $matcher ) {
		$in_title = false;
		foreach ( $matcher['patterns'] as $pattern ) {
			if ( preg_match( $pattern, $title ) ) { $in_title = true; break; }
		}
		if ( $in_title ) { $title_hits[] = $matcher['slug']; continue; }
		if ( array_intersect( $matcher['exact'], $categories ) ) { $other_hits[] = $matcher['slug']; continue; }
		foreach ( $matcher['patterns'] as $pattern ) {
			if ( '' !== $body && preg_match( $pattern, $body ) ) { $other_hits[] = $matcher['slug']; break; }
		}
	}
	return array_slice( array_values( array_unique( array_merge( $title_hits, $other_hits ) ) ), 0, 8 );
}
