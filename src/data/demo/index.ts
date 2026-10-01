import type {
  Artist,
  Member,
  Article,
  ForumCategory,
  ForumThread,
  ForumPost,
  ComebackEvent,
  Poll,
  User,
  CommunityPost,
  Badge,
  Comment,
  Notification,
  Video,
} from "@/types";

// Stylized, copyright-safe concept art per artist (no real likenesses).
import imgBts from "@/assets/artists/bts.jpg";
import imgBlackpink from "@/assets/artists/blackpink.jpg";
import imgNewjeans from "@/assets/artists/newjeans.jpg";
import imgLesserafim from "@/assets/artists/le-sserafim.jpg";
import imgAespa from "@/assets/artists/aespa.jpg";
import imgIve from "@/assets/artists/ive.jpg";
import imgStraykids from "@/assets/artists/stray-kids.jpg";
import imgTwice from "@/assets/artists/twice.jpg";
import imgSeventeen from "@/assets/artists/seventeen.jpg";
import imgItzy from "@/assets/artists/itzy.jpg";
import imgRiize from "@/assets/artists/riize.jpg";
import imgEnhypen from "@/assets/artists/enhypen.jpg";
import imgIu from "@/assets/artists/iu.jpg";
import imgTxt from "@/assets/artists/txt.jpg";
import imgGidle from "@/assets/artists/gidle.jpg";
import imgZerobaseone from "@/assets/artists/zerobaseone.jpg";

const artistImageBySlug: Record<string, string> = {
  bts: imgBts,
  blackpink: imgBlackpink,
  newjeans: imgNewjeans,
  "le-sserafim": imgLesserafim,
  aespa: imgAespa,
  ive: imgIve,
  "stray-kids": imgStraykids,
  twice: imgTwice,
  seventeen: imgSeventeen,
  itzy: imgItzy,
  riize: imgRiize,
  enhypen: imgEnhypen,
  iu: imgIu,
  txt: imgTxt,
  gidle: imgGidle,
  zerobaseone: imgZerobaseone,
};

// Fallback brand-color gradient placeholders for entities without bespoke art
// (members, generic comeback covers, author avatars, etc.).
const grad = (a: string, b: string, label: string) =>
  `data:image/svg+xml;utf8,${encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600'><defs><linearGradient id='g' x1='0' x2='1' y1='0' y2='1'><stop offset='0' stop-color='${a}'/><stop offset='1' stop-color='${b}'/></linearGradient></defs><rect width='800' height='600' fill='url(%23g)'/><text x='50%' y='50%' fill='white' font-family='sans-serif' font-size='56' font-weight='800' text-anchor='middle' dominant-baseline='middle' opacity='0.92'>${label}</text></svg>`,
  )}`;

export const placeholderImg = grad;

// ---------- Real artists ----------
type ArtistSeed = [
  slug: string,
  name: string,
  korean: string,
  type: Artist["type"],
  agency: string,
  debut: string,
  generation: Artist["generation"],
  fandom: string,
  c1: string,
  c2: string,
  followers: number,
  bio: string,
];

const artistSeeds: ArtistSeed[] = [
  [
    "bts",
    "BTS",
    "방탄소년단",
    "boy_group",
    "BIGHIT MUSIC (HYBE)",
    "2013-06-13",
    3,
    "ARMY",
    "#7B68EE",
    "#3A1F8E",
    75_000_000,
    "Seven-member group whose genre-spanning catalog and global advocacy made them the first K-pop act to top the Billboard 200 and headline UN events.",
  ],
  [
    "blackpink",
    "BLACKPINK",
    "블랙핑크",
    "girl_group",
    "YG Entertainment",
    "2016-08-08",
    3,
    "BLINK",
    "#FF1493",
    "#000000",
    56_000_000,
    "Four-member group that broke records as the first K-pop girl group to headline Coachella and tour stadiums worldwide.",
  ],
  [
    "newjeans",
    "NewJeans",
    "뉴진스",
    "girl_group",
    "ADOR",
    "2022-07-22",
    4,
    "Bunnies",
    "#87CEEB",
    "#1E3A8A",
    12_500_000,
    "Five-member group that redefined Y2K nostalgia in 4th generation K-pop with viral debut tracks 'Attention' and 'Hype Boy'.",
  ],
  [
    "le-sserafim",
    "LE SSERAFIM",
    "르세라핌",
    "girl_group",
    "Source Music (HYBE)",
    "2022-05-02",
    4,
    "FEARNOT",
    "#6B5B95",
    "#1A1147",
    9_800_000,
    "Five-member group whose name is an anagram of 'I'm Fearless,' known for confident anthems like 'Antifragile' and 'EASY'.",
  ],
  [
    "aespa",
    "aespa",
    "에스파",
    "girl_group",
    "SM Entertainment",
    "2020-11-17",
    4,
    "MY",
    "#00E5FF",
    "#0A2540",
    15_200_000,
    "Four-member group built around a hybrid metaverse concept, with hits 'Next Level,' 'Spicy,' and 'Supernova.'",
  ],
  [
    "ive",
    "IVE",
    "아이브",
    "girl_group",
    "Starship Entertainment",
    "2021-12-01",
    4,
    "DIVE",
    "#4B0082",
    "#FFD700",
    11_400_000,
    "Six-member group that debuted with 'ELEVEN' and dominated 2022–2024 charts via 'LOVE DIVE,' 'After LIKE,' and 'I AM.'",
  ],
  [
    "stray-kids",
    "Stray Kids",
    "스트레이 키즈",
    "boy_group",
    "JYP Entertainment",
    "2018-03-25",
    4,
    "STAY",
    "#DC143C",
    "#1A1A1A",
    22_000_000,
    "Self-producing eight-member group that scored five consecutive No. 1 debuts on the Billboard 200.",
  ],
  [
    "twice",
    "TWICE",
    "트와이스",
    "girl_group",
    "JYP Entertainment",
    "2015-10-20",
    3,
    "ONCE",
    "#FF69B4",
    "#FF8C00",
    18_700_000,
    "Nine-member group whose discography from 'TT' to 'I CAN'T STOP ME' set the template for late-2010s K-pop pop.",
  ],
  [
    "seventeen",
    "SEVENTEEN",
    "세븐틴",
    "boy_group",
    "PLEDIS Entertainment (HYBE)",
    "2015-05-26",
    3,
    "CARAT",
    "#FFB6C1",
    "#FFFFFF",
    20_300_000,
    "Self-producing 13-member group split into vocal, hip-hop, and performance units; broke first-week sales records with 'FML' and '17 IS RIGHT HERE.'",
  ],
  [
    "itzy",
    "ITZY",
    "있지",
    "girl_group",
    "JYP Entertainment",
    "2019-02-12",
    4,
    "MIDZY",
    "#FF4500",
    "#2E0066",
    9_200_000,
    "Five-member group that debuted with 'DALLA DALLA' and built a catalog around self-love anthems.",
  ],
  [
    "riize",
    "RIIZE",
    "라이즈",
    "boy_group",
    "SM Entertainment",
    "2023-09-04",
    5,
    "BRIIZE",
    "#FFD700",
    "#0A1F44",
    4_500_000,
    "Seven-member group blending emo-pop and R&B; debuted with 'Get A Guitar' and 'Memories.'",
  ],
  [
    "enhypen",
    "ENHYPEN",
    "엔하이픈",
    "boy_group",
    "BELIFT LAB (HYBE)",
    "2020-11-30",
    4,
    "ENGENE",
    "#B22222",
    "#0A0A0A",
    11_800_000,
    "Seven-member group formed via 'I-LAND,' with anthems 'Bite Me,' 'Sweet Venom' and 'XO (Only If You Say Yes).'",
  ],
  [
    "iu",
    "IU",
    "아이유",
    "soloist",
    "EDAM Entertainment",
    "2008-09-18",
    2,
    "UAENA",
    "#E91E63",
    "#4A0033",
    28_400_000,
    "Singer-songwriter and actress widely regarded as Korea's 'nation's little sister'; record-holding solo concert artist.",
  ],
  [
    "txt",
    "TOMORROW X TOGETHER",
    "투모로우바이투게더",
    "boy_group",
    "BIGHIT MUSIC (HYBE)",
    "2019-03-04",
    4,
    "MOA",
    "#87CEFA",
    "#1E3A8A",
    14_900_000,
    "Five-member coming-of-age concept group; 'Sugar Rush Ride,' 'Chasing That Feeling' and 'Deja Vu' became global hits.",
  ],
  [
    "gidle",
    "(G)I-DLE",
    "(여자)아이들",
    "girl_group",
    "CUBE Entertainment",
    "2018-05-02",
    4,
    "NEVERLAND",
    "#C71585",
    "#2D0033",
    10_100_000,
    "Self-producing five-member group; 'TOMBOY,' 'Queencard' and 'Super Lady' defined their 2022–2024 run.",
  ],
  [
    "zerobaseone",
    "ZEROBASEONE",
    "제로베이스원",
    "boy_group",
    "WAKEONE",
    "2023-07-10",
    5,
    "ZEROSE",
    "#1E90FF",
    "#0A0033",
    5_600_000,
    "Nine-member group formed through 'Boys Planet'; debuted with the million-seller 'YOUTH IN THE SHADE.'",
  ],
];

const artistsData: Artist[] = artistSeeds.map(
  ([slug, name, korean, type, agency, debut, generation, fandom, c1, c2, followers, bio], i) => ({
    id: `ar_${i + 1}`,
    slug,
    name,
    koreanName: korean,
    type,
    agency,
    debutDate: debut,
    fandomName: fandom,
    generation,
    status: "active",
    nationality: "South Korea",
    bio,
    image: artistImageBySlug[slug] ?? grad(c1, c2, name),
    followerCount: followers,
    memberIds: [],
    socialLinks: {
      official: `https://${slug.replace(/-/g, "")}.official.example`,
      youtube: `https://youtube.com/@${slug.replace(/-/g, "")}`,
      instagram: `https://instagram.com/${slug.replace(/-/g, "")}`,
      x: `https://x.com/${slug.replace(/-/g, "")}`,
    },
  }),
);

// ---------- Real members ----------
type MemberSeed = [
  stage: string,
  korean: string,
  birthday: string,
  nationality: string,
  position: string[],
  mbti?: string,
];

const memberRoster: Record<string, MemberSeed[]> = {
  bts: [
    ["RM", "김남준", "1994-09-12", "Korean", ["Leader", "Main Rapper"], "INTP"],
    ["Jin", "김석진", "1992-12-04", "Korean", ["Vocalist", "Visual"], "INTP"],
    ["Suga", "민윤기", "1993-03-09", "Korean", ["Lead Rapper", "Producer"], "INFP"],
    ["J-Hope", "정호석", "1994-02-18", "Korean", ["Main Dancer", "Sub Rapper"], "ESFJ"],
    ["Jimin", "박지민", "1995-10-13", "Korean", ["Main Dancer", "Lead Vocalist"], "ENFJ"],
    ["V", "김태형", "1995-12-30", "Korean", ["Vocalist", "Visual"], "ENFP"],
    ["Jungkook", "전정국", "1997-09-01", "Korean", ["Main Vocalist", "Maknae"], "INFP"],
  ],
  blackpink: [
    ["Jisoo", "김지수", "1995-01-03", "Korean", ["Vocalist", "Visual"], "ISFP"],
    ["Jennie", "제니", "1996-01-16", "Korean", ["Main Rapper", "Lead Vocalist"], "ENFP"],
    [
      "Rosé",
      "박채영",
      "1997-02-11",
      "New Zealander–Korean",
      ["Main Vocalist", "Lead Dancer"],
      "ESFP",
    ],
    [
      "Lisa",
      "ลลิษา มโนบาล",
      "1997-03-27",
      "Thai",
      ["Main Dancer", "Lead Rapper", "Maknae"],
      "ENFP",
    ],
  ],
  newjeans: [
    ["Minji", "김민지", "2004-05-07", "Korean", ["Leader", "Vocalist"], "INFJ"],
    ["Hanni", "팜티한", "2004-10-06", "Vietnamese-Australian", ["Vocalist"], "ENFP"],
    ["Danielle", "다니엘", "2005-04-11", "Korean–Australian", ["Vocalist"], "ENFP"],
    ["Haerin", "강해린", "2006-05-15", "Korean", ["Vocalist"], "ISFP"],
    ["Hyein", "이혜인", "2008-04-21", "Korean", ["Vocalist", "Maknae"], "ESTP"],
  ],
  "le-sserafim": [
    ["Sakura", "사쿠라", "1998-03-19", "Japanese", ["Vocalist"], "ENTJ"],
    ["Chaewon", "김채원", "2000-08-01", "Korean", ["Leader", "Vocalist"], "INFP"],
    ["Yunjin", "허윤진", "2001-10-08", "Korean–American", ["Main Vocalist"], "ENFP"],
    ["Kazuha", "카즈하", "2003-08-09", "Japanese", ["Main Dancer", "Vocalist"], "ISFP"],
    ["Eunchae", "홍은채", "2006-11-10", "Korean", ["Vocalist", "Maknae"], "ENFP"],
  ],
  aespa: [
    ["Karina", "유지민", "2000-04-11", "Korean", ["Leader", "Main Dancer"], "ENFP"],
    ["Giselle", "우치노미야 아이리", "2000-10-30", "Japanese-Korean", ["Rapper"], "ENTP"],
    ["Winter", "김민정", "2001-01-01", "Korean", ["Main Vocalist"], "ISFP"],
    ["Ningning", "닝닝", "2002-10-23", "Chinese", ["Main Vocalist", "Maknae"], "ESFP"],
  ],
  ive: [
    ["Yujin", "안유진", "2003-09-01", "Korean", ["Leader", "Vocalist"], "ENFP"],
    ["Gaeul", "김가을", "2002-09-24", "Korean", ["Rapper"], "ENTP"],
    ["Rei", "나오이 레이", "2004-02-03", "Japanese", ["Rapper", "Vocalist"], "ENFP"],
    ["Wonyoung", "장원영", "2004-08-31", "Korean", ["Visual", "Vocalist"], "ESFJ"],
    ["Liz", "김지원", "2004-11-21", "Korean", ["Main Vocalist"], "ENFP"],
    ["Leeseo", "이현서", "2007-02-21", "Korean", ["Vocalist", "Maknae"], "ESFP"],
  ],
  "stray-kids": [
    ["Bang Chan", "방찬", "1997-10-03", "Korean–Australian", ["Leader", "Producer"], "ENFJ"],
    ["Lee Know", "이민호", "1998-10-25", "Korean", ["Main Dancer", "Vocalist"], "ISFJ"],
    ["Changbin", "서창빈", "1999-08-11", "Korean", ["Main Rapper", "Producer"], "ESTJ"],
    ["Hyunjin", "황현진", "2000-03-20", "Korean", ["Main Dancer", "Rapper"], "INFJ"],
    ["Han", "한지성", "2000-09-14", "Korean", ["Main Rapper", "Producer"], "ESFP"],
    ["Felix", "이용복", "2000-09-15", "Korean–Australian", ["Lead Dancer", "Rapper"], "ISFP"],
    ["Seungmin", "김승민", "2000-09-22", "Korean", ["Main Vocalist"], "ESTJ"],
    ["I.N", "양정인", "2001-02-08", "Korean", ["Vocalist", "Maknae"], "ESFP"],
  ],
  twice: [
    ["Nayeon", "임나연", "1995-09-22", "Korean", ["Lead Vocalist"], "ESTP"],
    ["Jeongyeon", "유경연", "1996-11-01", "Korean", ["Lead Vocalist"], "ISFP"],
    ["Momo", "히라이 모모", "1996-11-09", "Japanese", ["Main Dancer"], "ESFP"],
    ["Sana", "미나토자키 사나", "1996-12-29", "Japanese", ["Vocalist"], "ESFJ"],
    ["Jihyo", "박지효", "1997-02-01", "Korean", ["Leader", "Main Vocalist"], "ESFJ"],
    ["Mina", "묘이 미나", "1997-03-24", "Japanese", ["Main Dancer", "Vocalist"], "INTP"],
    ["Dahyun", "김다현", "1998-05-28", "Korean", ["Rapper"], "ESTP"],
    ["Chaeyoung", "손채영", "1999-04-23", "Korean", ["Main Rapper"], "INFP"],
    ["Tzuyu", "쯔위", "1999-06-14", "Taiwanese", ["Lead Dancer", "Maknae"], "ISTP"],
  ],
  seventeen: [
    ["S.Coups", "최승철", "1995-08-08", "Korean", ["Leader", "Hip-Hop Unit"], "ESTJ"],
    ["Jeonghan", "윤정한", "1995-10-04", "Korean", ["Vocal Unit"], "INFP"],
    ["Joshua", "홍지수", "1995-12-30", "Korean–American", ["Vocal Unit"], "ISFJ"],
    ["Jun", "문준휘", "1996-06-10", "Chinese", ["Performance Unit"], "ESFP"],
    ["Hoshi", "권순영", "1996-06-15", "Korean", ["Performance Unit Leader"], "ENFP"],
    ["Wonwoo", "전원우", "1996-07-17", "Korean", ["Hip-Hop Unit"], "INTP"],
    ["Woozi", "이지훈", "1996-11-22", "Korean", ["Vocal Unit Leader", "Producer"], "INFP"],
    ["DK", "이석민", "1997-02-18", "Korean", ["Main Vocalist"], "ESFJ"],
    ["Mingyu", "김민규", "1997-04-06", "Korean", ["Hip-Hop Unit", "Visual"], "ENFP"],
    ["The8", "서명호", "1997-11-07", "Chinese", ["Performance Unit"], "INFP"],
    ["Seungkwan", "부승관", "1998-01-16", "Korean", ["Vocal Unit"], "ENFJ"],
    ["Vernon", "한솔", "1998-02-18", "Korean–American", ["Hip-Hop Unit"], "ISFP"],
    ["Dino", "이찬", "1999-02-11", "Korean", ["Performance Unit", "Maknae"], "ESFP"],
  ],
  itzy: [
    ["Yeji", "황예지", "2000-05-26", "Korean", ["Leader", "Main Dancer"], "ENFP"],
    ["Lia", "최지수", "2000-07-21", "Korean", ["Main Vocalist"], "INFP"],
    ["Ryujin", "신류진", "2001-04-17", "Korean", ["Main Rapper"], "ENFP"],
    ["Chaeryeong", "이채령", "2001-06-05", "Korean", ["Main Dancer"], "INFP"],
    ["Yuna", "신유나", "2003-12-09", "Korean", ["Visual", "Maknae"], "ESFP"],
  ],
  riize: [
    ["Shotaro", "쇼타로", "2000-11-25", "Japanese", ["Main Dancer"], "ISFP"],
    ["Eunseok", "이은석", "2002-02-19", "Korean", ["Visual", "Vocalist"], "INTJ"],
    ["Sungchan", "정성찬", "2001-09-13", "Korean", ["Rapper"], "ENFP"],
    ["Wonbin", "박원빈", "2004-09-19", "Korean", ["Visual", "Vocalist"], "INFP"],
    ["Seunghan", "이승한", "2004-11-18", "Korean", ["Vocalist"], "ESFP"],
    ["Sohee", "석민혁", "2004-12-25", "Korean", ["Dancer"], "ISFP"],
    ["Anton", "이찬영", "2006-03-08", "Korean–American", ["Vocalist", "Maknae"], "INFP"],
  ],
  enhypen: [
    ["Heeseung", "이희승", "2001-10-15", "Korean", ["Main Vocalist", "Dancer"], "INTP"],
    ["Jay", "박종성", "2002-04-20", "Korean–American", ["Rapper", "Vocalist"], "ESFJ"],
    ["Jake", "심재윤", "2002-11-15", "Korean–Australian", ["Vocalist"], "ENFP"],
    ["Sunghoon", "박성훈", "2002-12-08", "Korean", ["Main Dancer", "Vocalist"], "ISTP"],
    ["Sunoo", "김선우", "2003-06-24", "Korean", ["Vocalist"], "ESFJ"],
    ["Jungwon", "양정원", "2004-02-09", "Korean", ["Leader", "Vocalist"], "ISTJ"],
    ["Ni-ki", "니키", "2005-12-09", "Japanese", ["Main Dancer", "Maknae"], "ESTP"],
  ],
  txt: [
    ["Soobin", "최수빈", "2000-12-05", "Korean", ["Leader", "Vocalist"], "ISFP"],
    ["Yeonjun", "최연준", "1999-09-13", "Korean", ["Main Dancer", "Rapper"], "ENFP"],
    ["Beomgyu", "최범규", "2001-03-13", "Korean", ["Vocalist"], "ENFP"],
    ["Taehyun", "강태현", "2002-02-05", "Korean", ["Main Vocalist"], "ISTP"],
    ["Huening Kai", "휴닝카이", "2002-08-14", "Korean–American", ["Vocalist", "Maknae"], "ESFJ"],
  ],
  gidle: [
    ["Miyeon", "조미연", "1997-01-31", "Korean", ["Main Vocalist"], "ISFJ"],
    ["Minnie", "민니", "1997-10-23", "Thai", ["Lead Vocalist"], "INFP"],
    ["Soyeon", "전소연", "1998-08-26", "Korean", ["Leader", "Main Rapper", "Producer"], "ENTP"],
    ["Yuqi", "송우기", "1999-09-23", "Chinese", ["Vocalist", "Rapper"], "ENFP"],
    ["Shuhua", "예슈화", "2000-01-06", "Taiwanese", ["Vocalist", "Visual", "Maknae"], "ESFP"],
  ],
  zerobaseone: [
    ["Sung Hanbin", "성한빈", "2001-07-08", "Korean", ["Leader", "Main Dancer"], "ISFJ"],
    ["Kim Jiwoong", "김지웅", "1998-09-10", "Korean", ["Visual"], "ISFP"],
    ["Zhang Hao", "장하오", "2001-04-25", "Chinese", ["Main Vocalist"], "ENFJ"],
    ["Seok Matthew", "석매튜", "2002-09-18", "Korean–Canadian", ["Vocalist"], "ENFP"],
    ["Kim Taerae", "김태래", "2003-06-19", "Korean", ["Main Vocalist"], "ISFP"],
    ["Ricky", "리키", "2005-02-25", "Taiwanese", ["Vocalist"], "ESFP"],
    ["Kim Gyuvin", "김규빈", "2005-04-05", "Korean", ["Rapper"], "ENFP"],
    ["Park Gunwook", "박건욱", "2005-08-10", "Korean", ["Rapper"], "ISFP"],
    ["Han Yujin", "한유진", "2007-04-25", "Korean", ["Vocalist", "Maknae"], "INFP"],
  ],
};

const membersData: Member[] = [];
artistsData.forEach((artist) => {
  if (artist.type === "soloist") return;
  const roster = memberRoster[artist.slug] ?? [];
  roster.forEach(([stage, korean, birthday, nationality, position, mbti]) => {
    const id = `mb_${artist.id}_${stage.toLowerCase().replace(/[^a-z0-9]+/g, "")}`;
    membersData.push({
      id,
      slug: `${artist.slug}-${stage.toLowerCase().replace(/[^a-z0-9]+/g, "-")}`,
      stageName: stage,
      fullName: stage,
      koreanName: korean,
      birthday,
      nationality,
      groupId: artist.id,
      position,
      mbti,
      image: grad("#1a1040", "#ff6b9d", stage),
      facts: [
        `Member of ${artist.name}`,
        `Position: ${position.join(", ")}`,
        `Debut: ${new Date(artist.debutDate).toLocaleDateString()}`,
      ],
    });
    artist.memberIds.push(id);
  });
});

// ---------- Forum categories ----------
const categoriesData: ForumCategory[] = [
  ["general", "General K-pop Discussion", "Everything K-pop — open chat for all groups", "💬"],
  ["comebacks", "Comebacks & Debuts", "Track upcoming releases and rookie debuts", "🎵"],
  ["news-reactions", "News Reactions", "Discuss the latest headlines and industry moves", "📰"],
  ["fandoms", "Artist Fandoms", "Dedicated spaces for ARMY, BLINK, ONCE, CARAT and more", "💖"],
  ["concerts", "Concerts & Tours", "World tours, ticket help, venue tips", "🎤"],
  ["albums-merch", "Albums & Merch", "Unboxings, photocard trades, collection talk", "💿"],
  ["fashion", "Styling & Fashion", "Stage looks, airport fashion, brand ambassadors", "👗"],
  ["fan-art", "Fan Art & Memes", "Creative fan content from around the world", "🎨"],
].map(([slug, name, description, icon], i) => ({
  id: `cat_${i + 1}`,
  slug: slug as string,
  name: name as string,
  description: description as string,
  icon: icon as string,
  threadCount: 48 + i * 17,
  postCount: 1240 + i * 320,
  rules: ["Stay respectful — no fanwars", "Tag spoilers", "Cite sources for news posts"],
}));

// ---------- Real news / editorial articles ----------
type ArticleSeed = {
  title: string;
  subtitle: string;
  excerpt: string;
  body: string;
  category: string;
  tags: string[];
  artistSlug: string;
  daysAgo: number;
  views: number;
  comments: number;
  reactions: number;
  readingTime: number;
};

const articleSeeds: ArticleSeed[] = [
  {
    title: "BTS reunite as a full group for first time since military service concluded",
    subtitle: "All seven members complete mandatory enlistment and confirm 2026 group projects.",
    excerpt:
      "With Suga's discharge in June 2025, BTS officially reunited as a seven-member group for the first time in nearly three years — and HYBE has now confirmed group activities are underway.",
    body: "<p>After Jin became the first member discharged from mandatory military service in <strong>June 2024</strong>, the remaining members of BTS — J-Hope, RM, V, Jimin, Jungkook and Suga — completed their enlistment periods on a rolling schedule that wrapped up in mid-2025.</p><h2>What's next</h2><p>HYBE has confirmed full-group activities for 2026, including new music and a world tour. The group's last full studio album was 'Proof' (2022) before the members entered service one by one.</p><h2>Solo runs continue</h2><p>Each member built a substantial solo catalog during the hiatus: Jimin's 'FACE' and 'MUSE,' Jungkook's 'GOLDEN,' Suga's Agust D trilogy concluding with 'D-DAY,' V's 'Layover,' RM's 'Right Place, Wrong Person,' J-Hope's 'HOPE ON THE STREET' and Jin's 'Happy.'</p><blockquote>\"We promised ARMY we'd come back together, and we're keeping that promise.\"</blockquote>",
    category: "Music",
    tags: ["bts", "comeback", "hybe", "military"],
    artistSlug: "bts",
    daysAgo: 1,
    views: 482_300,
    comments: 1840,
    reactions: 21_400,
    readingTime: 6,
  },
  {
    title: "BLACKPINK announce 'DEADLINE' world tour with first stadium dates in Seoul",
    subtitle: "Group renews with YG for group activities while solo contracts remain separate.",
    excerpt:
      "BLACKPINK return to the stage with the 'DEADLINE' tour, opening at Seoul's Goyang Stadium before a multi-continent run.",
    body: "<p>YG Entertainment confirmed BLACKPINK's reunion tour, titled <strong>'DEADLINE,'</strong> with opening shows at Goyang Stadium in July 2025 followed by stops across North America, Europe and Asia.</p><h2>The group-vs-solo split</h2><p>All four members — Jisoo, Jennie, Rosé and Lisa — renewed for group activities only. Jisoo launched BLISSOO, Jennie founded ODD ATELIER, Rosé signed with Atlantic Records, and Lisa launched LLOUD.</p><h2>New music</h2><p>The tour is expected to coincide with the group's first new music since 'BORN PINK' (2022).</p>",
    category: "Tour",
    tags: ["blackpink", "tour", "yg"],
    artistSlug: "blackpink",
    daysAgo: 2,
    views: 391_500,
    comments: 1210,
    reactions: 18_900,
    readingTime: 5,
  },
  {
    title: "NewJeans–ADOR dispute: the timeline so far",
    subtitle:
      "From Min Hee-jin's removal to contract termination claims and ongoing court hearings.",
    excerpt:
      "A breakdown of every major development in the dispute between NewJeans and ADOR, from spring 2024 through the latest court rulings.",
    body: "<p>The conflict between NewJeans and ADOR — a HYBE sublabel — escalated when CEO <strong>Min Hee-jin</strong> was removed in August 2024. The members held a public press conference in November 2024 declaring their exclusive contracts terminated.</p><h2>Court status</h2><p>Seoul courts have since issued multiple injunctions ordering the members to honor their contracts while the broader case proceeds. The members briefly performed independently under the name 'NJZ' before pausing activities.</p><h2>What fans should know</h2><p>Bunnies have organized peaceful support actions globally. The legal process is expected to continue throughout 2026.</p>",
    category: "Business",
    tags: ["newjeans", "ador", "hybe", "industry"],
    artistSlug: "newjeans",
    daysAgo: 3,
    views: 612_700,
    comments: 4520,
    reactions: 9_800,
    readingTime: 8,
  },
  {
    title:
      "aespa's 'Supernova' becomes longest-running No. 1 by a girl group on Circle Digital Chart",
    subtitle: "The lead single from 'Armageddon' continues its multi-month chart run.",
    excerpt:
      "aespa's 'Supernova' has cemented its place in K-pop history, becoming the longest-running No. 1 by a girl group on Korea's Circle Digital Chart.",
    body: "<p>Released in May 2024 as the pre-release single from <strong>'Armageddon,'</strong> 'Supernova' has dominated Korean streaming and download charts well into 2025, eventually breaking Brave Girls' 'Rollin'' record for longest girl-group chart reign.</p><h2>Tour update</h2><p>The group's 'Synk: Parallel Line' world tour — their largest production to date — wrapped its first leg in late 2024 and continues with additional dates announced for 2026.</p>",
    category: "Charts",
    tags: ["aespa", "sm", "charts"],
    artistSlug: "aespa",
    daysAgo: 4,
    views: 218_400,
    comments: 620,
    reactions: 11_200,
    readingTime: 4,
  },
  {
    title: "Stray Kids extend Billboard 200 streak with sixth consecutive No. 1 album",
    subtitle: "The self-producing octet continues a record unprecedented for any K-pop act.",
    excerpt:
      "Stray Kids have now charted six consecutive studio releases at No. 1 on the Billboard 200 — a streak no other K-pop group has matched.",
    body: "<p>From <strong>'ODDINARY'</strong> (2022) through their latest release, Stray Kids have debuted at No. 1 on the Billboard 200 every time — a streak that includes 'MAXIDENT,' '5-STAR,' 'ROCK-STAR,' 'ATE' and the most recent project.</p><h2>Touring engine</h2><p>The 'dominATE' world tour has played stadiums across the US, Europe, Japan and Latin America, including back-to-back nights at Citi Field, Tokyo Dome and Estadio Vélez Sarsfield.</p>",
    category: "Charts",
    tags: ["stray-kids", "jyp", "billboard"],
    artistSlug: "stray-kids",
    daysAgo: 5,
    views: 287_100,
    comments: 940,
    reactions: 15_600,
    readingTime: 5,
  },
  {
    title: "LE SSERAFIM's 'EASY' era closes with confirmed first headlining world tour",
    subtitle:
      "Source Music details an expanded global itinerary built around 'EASY,' 'CRAZY' and 'HOT.'",
    excerpt:
      "Source Music has confirmed LE SSERAFIM's first headlining world tour, with stops across Asia, North America and Europe.",
    body: "<p>The 'EASY' mini-album cycle marked a creative turning point for LE SSERAFIM, swapping the maximalist concepts of <strong>'UNFORGIVEN'</strong> for stripped-back, smoky production. Follow-up singles 'CRAZY' and 'HOT' reinforced the group's pivot to a sleeker sound.</p><h2>Tour details</h2><p>The tour opens in Seoul before traveling to Tokyo, Singapore, Los Angeles, New York, London and Paris. Presale begins for FEARNOT membership holders before general onsale.</p>",
    category: "Tour",
    tags: ["le-sserafim", "hybe", "tour"],
    artistSlug: "le-sserafim",
    daysAgo: 6,
    views: 174_500,
    comments: 510,
    reactions: 8_100,
    readingTime: 4,
  },
  {
    title: "IVE confirm full-group return after solo and acting projects",
    subtitle: "Starship sets release window after Wonyoung, Yujin and Liz solo activities.",
    excerpt:
      "After a spread of solo activities — including Wonyoung's first single and Yujin and Liz's acting projects — IVE return as a full group.",
    body: "<p>Following <strong>'IVE SWITCH'</strong> and 'IVE EMPATHY,' IVE took a brief group hiatus to allow members to pursue solo projects. Wonyoung released her digital single, while Yujin and Liz took on acting roles.</p><h2>What to expect</h2><p>Starship has confirmed the full-group release window and a domestic fan-concert series, with international dates to follow.</p>",
    category: "Comeback",
    tags: ["ive", "starship", "comeback"],
    artistSlug: "ive",
    daysAgo: 7,
    views: 156_900,
    comments: 420,
    reactions: 7_400,
    readingTime: 4,
  },
  {
    title: "TWICE break Spotify record as longest-charting K-pop girl group",
    subtitle: "Nine years in, 'TT,' 'Fancy' and 'Feel Special' continue to gain new listeners.",
    excerpt:
      "TWICE have surpassed every other K-pop girl group on Spotify for cumulative monthly listeners across their full discography.",
    body: "<p>TWICE's catalog — anchored by <strong>'TT,' 'Fancy,' 'Feel Special'</strong> and the more recent 'SET ME FREE' and 'ONE SPARK' — has built a remarkably durable audience on Spotify, with the group passing key milestone listener counts across 2025.</p><h2>Tour and sub-units</h2><p>The 'READY TO BE' world tour wrapped one of the highest-grossing runs ever by a K-pop girl group, and sub-units MISAMO, IM NAYEON and JIHYO continue to release solo material.</p>",
    category: "Charts",
    tags: ["twice", "jyp", "spotify"],
    artistSlug: "twice",
    daysAgo: 8,
    views: 198_300,
    comments: 730,
    reactions: 9_200,
    readingTime: 5,
  },
  {
    title: "SEVENTEEN's '17 IS RIGHT HERE' surpasses 5 million copies sold worldwide",
    subtitle: "The 13-member group's best-of compilation becomes one of K-pop's biggest sellers.",
    excerpt:
      "SEVENTEEN's 'BEST ALBUM 17 IS RIGHT HERE' has crossed 5 million units globally, cementing the group's position as one of K-pop's top-selling acts.",
    body: "<p>SEVENTEEN's best-of compilation <strong>'17 IS RIGHT HERE'</strong> — anchored by 'MAESTRO' and 'LALALI' — has continued to sell through 2024–2025, joining 'FML' and 'SEVENTEENTH HEAVEN' in the group's million-seller streak.</p><h2>Member activities</h2><p>The Hip-Hop and Vocal units have released solo and unit material, including Woozi's solo work and Hoshi x Woozi's HxW unit project.</p>",
    category: "Sales",
    tags: ["seventeen", "hybe", "pledis"],
    artistSlug: "seventeen",
    daysAgo: 9,
    views: 142_600,
    comments: 380,
    reactions: 6_700,
    readingTime: 4,
  },
  {
    title: "IU sells out HER world tour with stadium dates in Seoul, LA and London",
    subtitle:
      "The 'nation's little sister' returns to the global stage with her largest tour to date.",
    excerpt:
      "Lee Ji-eun (IU)'s 'HER' world tour has sold out across multiple continents, including her largest-ever Seoul date at Seoul World Cup Stadium.",
    body: "<p>IU continues to expand her live footprint, with the <strong>'HER'</strong> world tour selling out venues across Asia, North America and Europe. The Seoul opener was held at Seoul World Cup Stadium — making her the first Korean female solo artist to headline the venue.</p><h2>Discography note</h2><p>Tour set draws from across her catalog — 'Palette,' 'Blueming,' 'LILAC,' 'strawberry moon,' 'Love wins all,' 'Shopper' and the recent mini-album.</p>",
    category: "Tour",
    tags: ["iu", "solo", "tour"],
    artistSlug: "iu",
    daysAgo: 10,
    views: 223_700,
    comments: 540,
    reactions: 12_400,
    readingTime: 5,
  },
  {
    title: "TOMORROW X TOGETHER chart globally with 'minisode 3: TOMORROW'",
    subtitle: "The fifth Korean mini-album debuts at No. 2 on the Billboard 200.",
    excerpt:
      "TXT's 'minisode 3: TOMORROW' continues the group's run of Billboard 200 top-five debuts, anchored by lead single 'Deja Vu.'",
    body: "<p>TOMORROW X TOGETHER continued their streak of high Billboard 200 debuts with the mini-album <strong>'minisode 3: TOMORROW,'</strong> led by single 'Deja Vu.' The group also released the Japanese album 'Star Chaser' and continue the 'ACT: PROMISE' world tour.</p>",
    category: "Charts",
    tags: ["txt", "hybe", "billboard"],
    artistSlug: "txt",
    daysAgo: 11,
    views: 138_800,
    comments: 410,
    reactions: 7_100,
    readingTime: 4,
  },
  {
    title: "(G)I-DLE's 'Super Lady' becomes one of 2024's most-streamed K-pop b-sides",
    subtitle: "Soyeon's self-produced anthem extends the group's PMA run.",
    excerpt:
      "Self-producing leader Soyeon continues to write hits for (G)I-DLE, with 'Super Lady' joining 'TOMBOY' and 'Queencard' in the group's PMA catalog.",
    body: "<p>(G)I-DLE's <strong>'Super Lady'</strong> — written and produced by leader Soyeon — became one of the most-streamed K-pop b-sides of 2024 and a Perfect All-Kill on Korean charts. The group's most recent project explores a darker, R&B-leaning sound.</p>",
    category: "Music",
    tags: ["gidle", "cube", "self-producing"],
    artistSlug: "gidle",
    daysAgo: 12,
    views: 121_500,
    comments: 340,
    reactions: 6_300,
    readingTime: 4,
  },
  {
    title: "RIIZE's 'Boom Boom Bass' goes viral, propelling 'RIIZING' to million-seller status",
    subtitle: "SM's new boy group reaches their first million-seller within a year of debut.",
    excerpt:
      "RIIZE's first mini-album 'RIIZING' crossed one million copies sold, driven by the viral chorus of 'Boom Boom Bass.'",
    body: "<p>SM's youngest boy group <strong>RIIZE</strong> reached million-seller status with the mini-album 'RIIZING,' which compiled their pre-release singles ('Get A Guitar,' 'Talk Saxy,' 'Impossible,' 'Love 119') with new lead 'Boom Boom Bass.'</p><h2>Member update</h2><p>Following SM's mid-2024 announcement, RIIZE continues as a six-member group, with the recent return of member Seunghan in 2025.</p>",
    category: "Music",
    tags: ["riize", "sm", "debut"],
    artistSlug: "riize",
    daysAgo: 13,
    views: 134_900,
    comments: 360,
    reactions: 6_800,
    readingTime: 4,
  },
  {
    title: "ENHYPEN's 'ROMANCE : UNTOLD' becomes their biggest first-week seller",
    subtitle: "The second studio album crosses 2.7 million copies in its opening week.",
    excerpt:
      "ENHYPEN's second studio album 'ROMANCE : UNTOLD' opened with a career-best sales week, debuting at No. 2 on the Billboard 200.",
    body: "<p>ENHYPEN's second studio album <strong>'ROMANCE : UNTOLD'</strong> — led by single 'XO (Only If You Say Yes)' — opened with the group's biggest sales week to date and earned a Billboard 200 No. 2 debut.</p><h2>WALK THE LINE</h2><p>The 'WALK THE LINE' world tour added stadium dates after multiple sellouts.</p>",
    category: "Sales",
    tags: ["enhypen", "hybe", "belift"],
    artistSlug: "enhypen",
    daysAgo: 14,
    views: 119_200,
    comments: 280,
    reactions: 6_200,
    readingTime: 4,
  },
  {
    title: "ITZY renew with JYP and confirm full-group activities continue",
    subtitle: "All five members signed group-activity renewals while exploring individual paths.",
    excerpt:
      "ITZY have signed renewals with JYP Entertainment for group activities, with members free to negotiate solo deals separately.",
    body: "<p>JYP confirmed that all five ITZY members renewed their group-activity contracts. The group celebrated by releasing the mini-album <strong>'GOLD,'</strong> a return to a brighter, dance-pop sound after the 'BORN TO BE' era.</p>",
    category: "Business",
    tags: ["itzy", "jyp", "contract"],
    artistSlug: "itzy",
    daysAgo: 16,
    views: 102_400,
    comments: 240,
    reactions: 5_400,
    readingTime: 4,
  },
  {
    title: "ZEROBASEONE wrap final activities ahead of contract conclusion",
    subtitle: "WAKEONE confirms the project group's final album and farewell concerts.",
    excerpt:
      "Project group ZEROBASEONE, formed via Mnet's 'Boys Planet,' is preparing its final activities ahead of contract expiration.",
    body: "<p>As a project group with a defined contract length, <strong>ZEROBASEONE</strong> are preparing for their final activities. WAKEONE has confirmed a farewell album and concert series; individual members are expected to debut in new groups or as soloists thereafter.</p>",
    category: "Business",
    tags: ["zerobaseone", "wakeone", "project-group"],
    artistSlug: "zerobaseone",
    daysAgo: 18,
    views: 96_800,
    comments: 310,
    reactions: 4_900,
    readingTime: 4,
  },
];

const articlesData: Article[] = articleSeeds.map((s, i) => {
  const artist = artistsData.find((a) => a.slug === s.artistSlug)!;
  return {
    id: `art_${i + 1}`,
    slug: s.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-|-$/g, "")
      .slice(0, 80),
    title: s.title,
    subtitle: s.subtitle,
    excerpt: s.excerpt,
    content: s.body,
    featuredImage: artist.image,
    author: ["Mina Kang", "Daniel Rivera", "Sora Lee", "Jamie Park"][i % 4],
    authorAvatar: grad("#ff6b9d", "#6b8eff", "K"),
    category: s.category,
    tags: s.tags,
    relatedArtistIds: [artist.id],
    language: "en",
    source: "editorial",
    status: "published",
    viewCount: s.views,
    commentCount: s.comments,
    reactionCount: s.reactions,
    publishedAt: new Date(Date.now() - s.daysAgo * 86400000).toISOString(),
    modifiedAt: new Date(Date.now() - s.daysAgo * 86400000 + 3600000).toISOString(),
    readingTime: s.readingTime,
  };
});

// ---------- Users ----------
const usersData: User[] = [
  {
    id: "u_1",
    username: "armybunny",
    displayName: "ARMY Bunny",
    email: "member@kpopblog.test",
    role: "member",
    trustLevel: 2,
    points: 320,
    badges: ["b_1", "b_2"],
    followedArtists: ["ar_1", "ar_3"],
    createdAt: new Date(Date.now() - 90 * 86400000).toISOString(),
    avatar: grad("#7B68EE", "#FF1493", "A"),
    country: "US",
    language: "en",
  },
  {
    id: "u_2",
    username: "blinkmod",
    displayName: "BLINK Mod",
    email: "mod@kpopblog.test",
    role: "moderator",
    trustLevel: 4,
    points: 1840,
    badges: ["b_1", "b_5", "b_8"],
    followedArtists: ["ar_2"],
    createdAt: new Date(Date.now() - 365 * 86400000).toISOString(),
    avatar: grad("#FF1493", "#000000", "B"),
    country: "PH",
    language: "en",
  },
  {
    id: "u_3",
    username: "stay4skz",
    displayName: "STAY Editor",
    email: "editor@kpopblog.test",
    role: "editor",
    trustLevel: 4,
    points: 2400,
    badges: ["b_1", "b_4"],
    followedArtists: ["ar_7"],
    createdAt: new Date(Date.now() - 540 * 86400000).toISOString(),
    avatar: grad("#DC143C", "#1A1A1A", "S"),
    country: "KR",
    language: "ko",
  },
  {
    id: "u_4",
    username: "kpopblog_admin",
    displayName: "Editorial Admin",
    email: "admin@kpopblog.test",
    role: "admin",
    trustLevel: 5,
    points: 8200,
    badges: ["b_1", "b_5", "b_7", "b_8"],
    followedArtists: ["ar_1", "ar_2", "ar_5"],
    createdAt: new Date(Date.now() - 720 * 86400000).toISOString(),
    avatar: grad("#a06bff", "#ff6b9d", "K"),
    country: "KR",
    language: "en",
  },
  {
    id: "u_5",
    username: "mybyaespa",
    displayName: "MY Forever",
    email: "my@kpopblog.test",
    role: "trusted_member",
    trustLevel: 3,
    points: 720,
    badges: ["b_2", "b_3"],
    followedArtists: ["ar_5"],
    createdAt: new Date(Date.now() - 200 * 86400000).toISOString(),
    avatar: grad("#00E5FF", "#0A2540", "M"),
    country: "JP",
    language: "ja",
  },
];

// ---------- Forum threads ----------
const threadsData: ForumThread[] = [
  [
    "BTS full-group reunion: what we hope to hear on the comeback album",
    "cat_2",
    "ar_1",
    "Megathread",
    true,
  ],
  ["BLACKPINK DEADLINE tour: ticket lottery results & swap thread", "cat_5", "ar_2", "Help"],
  [
    "NewJeans / ADOR situation — please cite sources only",
    "cat_3",
    "ar_3",
    "News",
    true,
    false,
    true,
  ],
  ["aespa Supernova chart longevity — how did we get here?", "cat_1", "ar_5", "Analysis"],
  ["Stray Kids dominATE tour reaction: Seoul Night 1", "cat_5", "ar_7", "Concert"],
  ["LE SSERAFIM 'CRAZY' choreography breakdown", "cat_1", "ar_4", "Analysis"],
  ["IVE solo era roundup — Wonyoung, Yujin, Liz", "cat_4", "ar_6", "Discussion"],
  ["TWICE 9-member chemistry: favorite tracks?", "cat_4", "ar_8", "Appreciation"],
  ["SEVENTEEN unit ranking thread", "cat_4", "ar_9", "Discussion"],
  ["IU 'HER' tour fanmade lightstick guide", "cat_5", "ar_13", "Help"],
  ["TXT Deja Vu live arrangement is a masterpiece", "cat_1", "ar_14", "Appreciation"],
  ["(G)I-DLE Super Lady styling appreciation", "cat_7", "ar_15", "Appreciation"],
  ["RIIZE 7-member return — Seunghan welcome thread", "cat_4", "ar_11", "Discussion", true],
  ["ENHYPEN ROMANCE : UNTOLD listening party recap", "cat_2", "ar_12", "Discussion"],
  ["Weekly: Which 5th gen group are you watching closest?", "cat_1", "ar_11", "Weekly", true],
].map((row, i) => {
  const [title, categoryId, authorId, flair, pinned, locked, official, rumor] = row as [
    string,
    string,
    string,
    string,
    boolean?,
    boolean?,
    boolean?,
    boolean?,
  ];
  return {
    id: `th_${i + 1}`,
    slug: title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-|-$/g, "")
      .slice(0, 70),
    categoryId,
    title,
    body: "Welcome to this discussion. Please keep it civil, cite sources for news, and tag spoilers where appropriate.",
    authorId,
    flair,
    pinned: !!pinned,
    locked: !!locked,
    official: !!official,
    rumor: !!rumor,
    views: 1200 + i * 340,
    replies: 18 + i * 7,
    reactions: 42 + i * 11,
    lastActivityAt: new Date(Date.now() - i * 3600000).toISOString(),
    createdAt: new Date(Date.now() - i * 86400000).toISOString(),
  };
});

const postsData: ForumPost[] = [];
threadsData.forEach((t) => {
  const replies = [
    "Adding sources from the latest press release — link in the article roundup.",
    "The production on this era really stands out. The B-sides are stronger than the lead IMO.",
    "Anyone else getting tickets in the second presale? Coordinating a swap if so.",
    "Translation help: the Korean fancafe post can be summarized as a thank-you note to the fans.",
  ];
  for (let i = 0; i < 4; i++) {
    postsData.push({
      id: `p_${t.id}_${i}`,
      threadId: t.id,
      authorId: usersData[i % usersData.length].id,
      body: replies[i],
      reactions: 3 + i * 2,
      createdAt: new Date(Date.now() - (i + 1) * 1800000).toISOString(),
    });
  }
});

// ---------- Comebacks (real-world style upcoming events) ----------
const comebacksData: ComebackEvent[] = [
  [
    "ar_1",
    "BTS — Group comeback album",
    "album",
    21,
    "First full-group studio album since 'Proof' (2022).",
  ],
  [
    "ar_2",
    "BLACKPINK — DEADLINE Tour Seoul",
    "concert",
    5,
    "Opening night of the 2025–2026 world tour at Goyang Stadium.",
  ],
  [
    "ar_5",
    "aespa — Synk: Parallel Line Asia Leg",
    "concert",
    14,
    "Second leg of the world tour with new Tokyo Dome dates.",
  ],
  [
    "ar_7",
    "Stray Kids — dominATE Final Leg",
    "concert",
    9,
    "Final dominATE world-tour shows in Europe.",
  ],
  [
    "ar_4",
    "LE SSERAFIM — Headlining World Tour Seoul",
    "concert",
    28,
    "First headlining world tour kickoff in Seoul.",
  ],
  [
    "ar_6",
    "IVE — Full-group return",
    "album",
    19,
    "Comeback after solo activities by Wonyoung, Yujin and Liz.",
  ],
  ["ar_13", "IU — HER World Tour London", "concert", 34, "London date of the HER world tour."],
  [
    "ar_11",
    "RIIZE — New mini-album",
    "album",
    12,
    "Follow-up to 'RIIZING' featuring the full seven-member lineup.",
  ],
  [
    "ar_14",
    "TXT — ACT : PROMISE encore concerts",
    "concert",
    42,
    "Encore dates added after global tour sellouts.",
  ],
  [
    "ar_12",
    "ENHYPEN — WALK THE LINE Final Encore",
    "concert",
    7,
    "Final encore concert of the WALK THE LINE world tour.",
  ],
].map(([artistId, title, type, days, description], i) => ({
  id: `cb_${i + 1}`,
  artistId: artistId as string,
  title: title as string,
  type: type as ComebackEvent["type"],
  releaseAt: new Date(Date.now() + (days as number) * 86400000).toISOString(),
  description: description as string,
  image: artistsData.find((a) => a.id === artistId)?.image,
  threadId: i < threadsData.length ? threadsData[i].id : undefined,
}));

// ---------- Polls ----------
const pollsData: Poll[] = [
  {
    id: "po_1",
    slug: "best-comeback-2026-q2",
    title: "Best comeback of 2026 so far?",
    options: [
      { id: "o1", label: "BTS — full-group reunion", votes: 28_400 },
      { id: "o2", label: "BLACKPINK — DEADLINE", votes: 24_900 },
      { id: "o3", label: "aespa — Supernova era", votes: 18_700 },
      { id: "o4", label: "Stray Kids — latest album", votes: 16_200 },
    ],
    totalVotes: 88_200,
  },
  {
    id: "po_2",
    slug: "most-anticipated-tour",
    title: "Which tour are you most hyped for?",
    options: [
      { id: "o1", label: "BLACKPINK DEADLINE", votes: 14_300 },
      { id: "o2", label: "IU HER World Tour", votes: 11_900 },
      { id: "o3", label: "Stray Kids dominATE", votes: 9_400 },
      { id: "o4", label: "LE SSERAFIM Headlining Tour", votes: 7_200 },
    ],
    totalVotes: 42_800,
  },
  {
    id: "po_3",
    slug: "song-of-the-week",
    title: "Song of the week?",
    options: [
      { id: "o1", label: "aespa — Supernova", votes: 8_400 },
      { id: "o2", label: "ENHYPEN — XO (Only If You Say Yes)", votes: 6_700 },
      { id: "o3", label: "TWICE — ONE SPARK", votes: 5_900 },
      { id: "o4", label: "(G)I-DLE — Super Lady", votes: 5_200 },
    ],
    totalVotes: 26_200,
  },
  {
    id: "po_4",
    slug: "favorite-generation",
    title: "Favorite K-pop generation?",
    options: [
      { id: "o1", label: "2nd gen (TVXQ, Girls' Generation, BIGBANG)", votes: 11_300 },
      { id: "o2", label: "3rd gen (BTS, BLACKPINK, TWICE, SEVENTEEN)", votes: 19_700 },
      { id: "o3", label: "4th gen (NewJeans, aespa, IVE, LE SSERAFIM, Stray Kids)", votes: 22_400 },
      { id: "o4", label: "5th gen (RIIZE, ZEROBASEONE, KISS OF LIFE)", votes: 8_900 },
    ],
    totalVotes: 62_300,
  },
  {
    id: "po_5",
    slug: "best-debut-2023-2024",
    title: "Best debut of 2023–2024?",
    options: [
      { id: "o1", label: "RIIZE (SM)", votes: 6_900 },
      { id: "o2", label: "ZEROBASEONE (WAKEONE)", votes: 5_800 },
      { id: "o3", label: "BABYMONSTER (YG)", votes: 7_400 },
      { id: "o4", label: "ILLIT (BELIFT LAB)", votes: 4_600 },
    ],
    totalVotes: 24_700,
  },
];

// ---------- Community wall ----------
const communityWallBodies = [
  "Streaming the new BTS group track on repeat — back to seven feels unreal 💜",
  "BLACKPINK DEADLINE Goyang Day 1 was unforgettable. Lightstick ocean was insane.",
  "aespa Supernova is genuinely a once-in-a-generation b-side leak situation.",
  "Stray Kids Seoul encore was a religious experience. STAY forever.",
  "LE SSERAFIM EASY choreography is so detail-heavy on the close-ups.",
  "Translation help: NewJeans' latest fancafe message essentially thanks Bunnies for patience.",
  "IU's HER tour setlist makes me emotional every single time.",
  "RIIZE 7-member stage finally happened. Welcome back Seunghan 💛",
  "ENHYPEN ROMANCE : UNTOLD wins for best concept book design this year.",
  "(G)I-DLE Super Lady styling >> entire 2024 award show season.",
];

const communityWallData: CommunityPost[] = communityWallBodies.map((body, i) => ({
  id: `cw_${i + 1}`,
  authorId: usersData[i % usersData.length].id,
  body,
  language: ["en", "ko", "en", "en", "ja", "en", "en", "en", "es", "id"][i % 10],
  reactions: 24 + i * 8,
  createdAt: new Date(Date.now() - i * 1200000).toISOString(),
  artistId: artistsData[i % artistsData.length].id,
}));

// ---------- Badges ----------
const badgesData: Badge[] = [
  {
    id: "b_1",
    name: "First Post",
    description: "Made your first contribution",
    icon: "✨",
    color: "#ff6b9d",
  },
  {
    id: "b_2",
    name: "Comeback Watcher",
    description: "Tracked 5+ comebacks",
    icon: "🎵",
    color: "#6b8eff",
  },
  {
    id: "b_3",
    name: "Translation Helper",
    description: "Helped translate fan posts",
    icon: "🌐",
    color: "#6bd4ff",
  },
  {
    id: "b_4",
    name: "Artist Expert",
    description: "Deep knowledge of a specific artist",
    icon: "🎤",
    color: "#a06bff",
  },
  {
    id: "b_5",
    name: "Trusted Fan",
    description: "Reached trust level 4",
    icon: "🛡️",
    color: "#6bffb8",
  },
  {
    id: "b_6",
    name: "Helpful Reporter",
    description: "Filed accurate reports",
    icon: "🚨",
    color: "#ff6b6b",
  },
  {
    id: "b_7",
    name: "Poll Voter",
    description: "Voted in 10+ polls",
    icon: "🗳️",
    color: "#ffd56b",
  },
  {
    id: "b_8",
    name: "Community Builder",
    description: "Started discussions that grew",
    icon: "🌟",
    color: "#ff3060",
  },
];

// ---------- Comments ----------
const commentBodies = [
  "Great write-up — sourced and balanced. Thank you for citing the official statement.",
  "Can't wait for the album drop. Concept teasers have been incredible so far.",
  "The data on chart longevity here is fascinating, more like this please!",
  "Editor note appreciated — clears up a lot of the misinformation circulating.",
  "Adding context: the agency confirmed the tour dates in their fancafe post earlier today.",
];
const commentsData: Comment[] = articlesData.flatMap((a) =>
  commentBodies.slice(0, 3).map((body, i) => ({
    id: `c_${a.id}_${i}`,
    articleId: a.id,
    authorId: usersData[i % usersData.length].id,
    body,
    reactions: 4 + i * 2,
    createdAt: new Date(Date.now() - i * 600000).toISOString(),
  })),
);

// ---------- Notifications ----------
const notificationsData: Notification[] = [
  {
    id: "n_1",
    userId: "u_1",
    type: "reply",
    body: "BLINK Mod replied to your BTS reunion thread",
    url: `/thread/${threadsData[0].slug}`,
    read: false,
    createdAt: new Date().toISOString(),
  },
  {
    id: "n_2",
    userId: "u_1",
    type: "comeback",
    body: "BLACKPINK DEADLINE Seoul opens in 5 days",
    url: "/comebacks",
    read: false,
    createdAt: new Date().toISOString(),
  },
  {
    id: "n_3",
    userId: "u_1",
    type: "follow",
    body: "MY Forever followed you",
    read: true,
    createdAt: new Date().toISOString(),
  },
  {
    id: "n_4",
    userId: "u_1",
    type: "mention",
    body: "New: aespa 'Supernova' breaks Circle chart record",
    url: `/news/${articlesData[3].slug}`,
    read: false,
    createdAt: new Date().toISOString(),
  },
];

// ---------- Videos (real YouTube IDs so they can be embedded in-site) ----------
const videoSeeds: Array<
  [slug: string, title: string, category: string, youtubeId: string, duration: string]
> = [
  ["bts", "BTS — 'Dynamite' Official MV", "MV", "gdZLi9oWNZg", "3:43"],
  ["bts", "BTS — 'Butter' Official MV", "MV", "WMweEpGlu_U", "3:55"],
  ["bts", "BTS — 'Boy With Luv' feat. Halsey", "MV", "XsX3ATc3FbA", "3:50"],
  ["blackpink", "BLACKPINK — 'Pink Venom' MV", "MV", "gQlMMD8auMs", "3:08"],
  ["blackpink", "BLACKPINK — 'How You Like That' MV", "MV", "ioNng23DkIM", "3:10"],
  ["blackpink", "BLACKPINK — 'Shut Down' MV", "MV", "POe9SOEKotk", "2:56"],
  ["newjeans", "NewJeans — 'Super Shy' MV", "MV", "ArmDp-zijuc", "2:34"],
  ["newjeans", "NewJeans — 'OMG' MV", "MV", "sVTy_wmn5SU", "3:35"],
  ["newjeans", "NewJeans — 'ETA' MV", "MV", "jOTfBlKSQYY", "2:35"],
  ["le-sserafim", "LE SSERAFIM — 'ANTIFRAGILE' MV", "MV", "pyf8cbqyfPs", "3:01"],
  ["le-sserafim", "LE SSERAFIM — 'EASY' MV", "MV", "WfJgnA6Lwgg", "3:04"],
  ["le-sserafim", "LE SSERAFIM — 'UNFORGIVEN' feat. Nile Rodgers", "MV", "Qq00ND2DKQQ", "3:05"],
  ["aespa", "aespa — 'Supernova' MV", "MV", "phuiiNCxRMg", "2:58"],
  ["aespa", "aespa — 'Next Level' MV", "MV", "4TWR90KJl84", "3:48"],
  ["aespa", "aespa — 'Spicy' MV", "MV", "TGPGfaFvHyM", "3:15"],
  ["ive", "IVE — 'I AM' MV", "MV", "6ZUIwj3FgUY", "3:21"],
  ["ive", "IVE — 'LOVE DIVE' MV", "MV", "Y8JFxS1HlDo", "3:00"],
  ["ive", "IVE — 'After LIKE' MV", "MV", "F0B7HDiY-10", "2:56"],
  ["stray-kids", "Stray Kids — 'God's Menu' MV", "MV", "TQTlCHxyuu8", "3:30"],
  ["stray-kids", "Stray Kids — 'MANIAC' MV", "MV", "PCp2iXA1uLE", "3:30"],
  ["stray-kids", "Stray Kids — 'LALALALA' MV", "MV", "JsOOis4bBFg", "3:11"],
  ["twice", "TWICE — 'I CAN'T STOP ME' MV", "MV", "CM4CkVFmTds", "3:33"],
  ["twice", "TWICE — 'Fancy' MV", "MV", "kOHB85vDuow", "3:34"],
  ["twice", "TWICE — 'ONE SPARK' MV", "MV", "VmyP8N8sDjY", "3:14"],
  ["seventeen", "SEVENTEEN — 'MAESTRO' MV", "MV", "QYHJEoQzG54", "3:04"],
  ["seventeen", "SEVENTEEN — 'God of Music' MV", "MV", "VqGAXVpePpw", "3:01"],
  ["seventeen", "SEVENTEEN — 'Super' MV", "MV", "tT2Yj4qPlSc", "3:08"],
  ["itzy", "ITZY — 'WANNABE' MV", "MV", "lrFp79uXPi0", "3:24"],
  ["itzy", "ITZY — 'LOCO' MV", "MV", "BiSCXVT_a3o", "3:25"],
  ["riize", "RIIZE — 'Get A Guitar' MV", "MV", "iuJDhFRDx9M", "3:00"],
  ["riize", "RIIZE — 'Boom Boom Bass' MV", "MV", "EzM3pZ7tziA", "3:09"],
  ["enhypen", "ENHYPEN — 'Bite Me' MV", "MV", "yJ5O7Q1ZxNw", "2:55"],
  ["enhypen", "ENHYPEN — 'Sweet Venom' MV", "MV", "k6jqx9kZgPM", "3:09"],
  ["iu", "IU — 'LILAC' MV", "MV", "v7bnOxV4jAc", "3:46"],
  ["iu", "IU — 'Blueming' MV", "MV", "D1PvIWdJ8xo", "3:36"],
  ["iu", "IU — 'eight' feat. SUGA of BTS", "MV", "mrxXjBDxqA8", "2:55"],
  ["txt", "TXT — 'Sugar Rush Ride' MV", "MV", "Km1u9Hi-pNk", "3:01"],
  ["txt", "TXT — 'Chasing That Feeling' MV", "MV", "WkpZ-2nQHcA", "3:19"],
  ["gidle", "(G)I-DLE — 'TOMBOY' MV", "MV", "8df0OFhJ9Zo", "2:53"],
  ["gidle", "(G)I-DLE — 'Queencard' MV", "MV", "kSTudb6ZqWg", "3:08"],
  ["gidle", "(G)I-DLE — 'Super Lady' MV", "MV", "0jbVuusZsdE", "3:18"],
  ["zerobaseone", "ZEROBASEONE — 'In Bloom' MV", "MV", "_TG1XlqYYTQ", "3:18"],
  ["zerobaseone", "ZEROBASEONE — 'CRUSH' MV", "MV", "ddPbsGy26IM", "3:16"],
];

const videosData: Video[] = videoSeeds.map(([slug, title, category, youtubeId, duration], i) => {
  const a = artistsData.find((x) => x.slug === slug)!;
  return {
    id: `v_${i + 1}`,
    title,
    artistId: a.id,
    artistSlug: slug,
    category,
    youtubeId,
    thumbnail: `https://i.ytimg.com/vi/${youtubeId}/hqdefault.jpg`,
    duration,
  };
});

// ---------- Charts ----------
const chartSeeds = [
  ["Supernova", "aespa", +1],
  ["XO (Only If You Say Yes)", "ENHYPEN", -1],
  ["ONE SPARK", "TWICE", 0],
  ["Super Lady", "(G)I-DLE", +4],
  ["Magnetic", "ILLIT", -2],
  ["EASY", "LE SSERAFIM", +2],
  ["Boom Boom Bass", "RIIZE", +5],
  ["Standing Next to You", "Jung Kook", -3],
  ["LALALI", "SEVENTEEN", +1],
  ["Love wins all", "IU", 0],
];

const chartsData = chartSeeds.map(([title, artist, change], i) => ({
  rank: i + 1,
  title: title as string,
  artist: artist as string,
  change: change as number,
}));

// ---------- Export ----------
export const demoData = {
  artists: artistsData,
  members: membersData,
  articles: articlesData,
  categories: categoriesData,
  threads: threadsData,
  posts: postsData,
  comebacks: comebacksData,
  polls: pollsData,
  users: usersData,
  community: communityWallData,
  badges: badgesData,
  comments: commentsData,
  notifications: notificationsData,
  videos: videosData,
  charts: chartsData,
};

export type DemoData = typeof demoData;
