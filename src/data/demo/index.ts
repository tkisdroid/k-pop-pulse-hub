import type { Artist, Member, Article, ForumCategory, ForumThread, ForumPost, ComebackEvent, Poll, User, CommunityPost, Badge, Comment, Notification } from "@/types";

// Safe gradient placeholder images (no copyrighted material).
const grad = (a: string, b: string, label: string) =>
  `data:image/svg+xml;utf8,${encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600'><defs><linearGradient id='g' x1='0' x2='1' y1='0' y2='1'><stop offset='0' stop-color='${a}'/><stop offset='1' stop-color='${b}'/></linearGradient></defs><rect width='800' height='600' fill='url(%23g)'/><text x='50%' y='50%' fill='white' font-family='sans-serif' font-size='48' font-weight='700' text-anchor='middle' dominant-baseline='middle' opacity='0.85'>${label}</text></svg>`
  )}`;

export const placeholderImg = grad;

const artistsData: Artist[] = [
  ["aurora-bloom", "AURORA BLOOM", "girl_group", "Stellar Ent.", "2021-03-14", 4, "BLOOMERS", "#ff6b9d", "#a06bff"],
  ["lunar-eclipse", "LUNAR ECLIPSE", "boy_group", "Nova Records", "2019-09-21", 4, "ECLIPSERS", "#6b8eff", "#9d6bff"],
  ["neon-tide", "NEON TIDE", "girl_group", "Wave Music", "2022-06-10", 5, "TIDALS", "#6bd4ff", "#6b9dff"],
  ["midnight-orbit", "MIDNIGHT ORBIT", "boy_group", "Stellar Ent.", "2017-04-02", 3, "ORBITERS", "#2d2a4a", "#ff6bd4"],
  ["sora-han", "Sora Han", "soloist", "Indie/Self", "2020-11-11", 4, "SORALITES", "#ffb86b", "#ff6b9d"],
  ["velvet-roses", "VELVET ROSES", "girl_group", "Crimson Label", "2018-02-14", 3, "ROSEBUDS", "#ff4d6d", "#7d1f3a"],
  ["polar-lights", "POLAR LIGHTS", "coed", "Northstar Co.", "2023-01-20", 5, "AURORAS", "#a0f3ff", "#6b8eff"],
  ["kairos", "KAIROS", "boy_group", "Time Records", "2020-07-07", 4, "CHRONOS", "#6bffb8", "#1f7d4a"],
  ["minji-park", "Minji Park", "soloist", "Solo Atelier", "2016-08-08", 3, "MINJIS", "#ffd56b", "#ff6b6b"],
  ["echo-bay", "ECHO BAY", "band", "Tidewave", "2019-05-30", 3, "ECHOERS", "#6bcfff", "#1f4a7d"],
  ["scarlet-vow", "SCARLET VOW", "girl_group", "Crimson Label", "2024-03-03", 5, "VOWERS", "#ff3060", "#7d1f3a"],
  ["odyssey", "ODYSSEY", "boy_group", "Nova Records", "2014-12-01", 2, "VOYAGERS", "#5a3bff", "#1a1040"],
].map(([slug, name, type, agency, debutDate, generation, fandomName, c1, c2], i) => ({
  id: `ar_${i + 1}`,
  slug: slug as string,
  name: name as string,
  koreanName: "—",
  type: type as Artist["type"],
  agency: agency as string,
  debutDate: debutDate as string,
  fandomName: fandomName as string,
  generation: generation as Artist["generation"],
  status: "active",
  nationality: "South Korea",
  bio: `${name} is a ${type} known for genre-bending production and a global fanbase. This is fictional demo data only.`,
  image: grad(c1 as string, c2 as string, name as string),
  followerCount: 120000 + i * 84321,
  memberIds: [],
  socialLinks: { official: "#", youtube: "#", instagram: "#", x: "#" },
}));

const memberNames = ["Yuna", "Hana", "Riko", "Soobin", "Jihu", "Minho", "Ari", "Kaito", "Sena", "Joon", "Eun", "Lia"];
const positions = ["Leader", "Main Vocal", "Main Dancer", "Main Rapper", "Visual", "Maknae", "Sub Vocal"];

const membersData: Member[] = [];
artistsData.forEach((artist, gi) => {
  if (artist.type === "soloist") return;
  const count = artist.type === "band" ? 4 : 5;
  for (let i = 0; i < count; i++) {
    const name = memberNames[(gi + i) % memberNames.length] + (i + 1);
    const id = `mb_${artist.id}_${i}`;
    membersData.push({
      id,
      slug: `${artist.slug}-${name.toLowerCase()}`,
      stageName: name,
      fullName: `${name} Demo`,
      koreanName: "—",
      birthday: `200${(i + 1) % 5}-0${(i % 9) + 1}-1${i % 9}`,
      nationality: i % 3 === 0 ? "Korean" : i % 3 === 1 ? "Japanese" : "Chinese",
      groupId: artist.id,
      position: [positions[i % positions.length]],
      mbti: ["INFJ", "ENFP", "ISTP", "ESTJ", "INTP"][i % 5],
      image: grad("#1a1040", "#ff6b9d", name),
      facts: ["Loves photography", "Trained for 4 years", "Speaks 3 languages"],
    });
    artist.memberIds.push(id);
  }
});

const categoriesData: ForumCategory[] = [
  ["general", "General K-pop Discussion", "Everything K-pop", "💬"],
  ["comebacks", "Comebacks & Debuts", "Track upcoming releases", "🎵"],
  ["news-reactions", "News Reactions", "Discuss the latest stories", "📰"],
  ["fandoms", "Artist Fandoms", "Dedicated fandom spaces", "💖"],
  ["concerts", "Concerts & Tours", "Live shows & world tours", "🎤"],
  ["albums-merch", "Albums & Merch", "Unboxings, collections, trades", "💿"],
  ["fashion", "Styling & Fashion", "Stage styling and street fits", "👗"],
  ["fan-art", "Fan Art & Memes", "Creative fan content", "🎨"],
].map(([slug, name, description, icon], i) => ({
  id: `cat_${i + 1}`,
  slug: slug as string,
  name: name as string,
  description: description as string,
  icon: icon as string,
  threadCount: 12 + i * 3,
  postCount: 240 + i * 50,
  rules: ["Stay respectful", "No fanwars", "Use spoiler tags"],
}));

const articleTitles = [
  "AURORA BLOOM teases ethereal new mini-album with cinematic concept film",
  "LUNAR ECLIPSE breaks streaming records hours after midnight comeback",
  "NEON TIDE confirms first world tour spanning 18 cities",
  "MIDNIGHT ORBIT members renew with agency, plot full-group return",
  "Sora Han releases self-produced surprise single",
  "VELVET ROSES bring vintage Y2K aesthetic to award stage",
  "POLAR LIGHTS chart globally with debut B-side",
  "KAIROS announce documentary chronicling rookie year",
  "Minji Park earns first solo Daesang nomination",
  "ECHO BAY play sold-out homecoming show in Busan",
];

const articlesData: Article[] = articleTitles.map((title, i) => {
  const artist = artistsData[i % artistsData.length];
  const cat = ["Comeback", "Music", "Tour", "Business", "Music", "Fashion", "Charts", "Feature", "Awards", "Tour"][i];
  return {
    id: `ar_${i + 1}`,
    slug: title.toLowerCase().replace(/[^a-z0-9]+/g, "-").slice(0, 60),
    title,
    subtitle: "A fully fictional demo article for the KpopBlog platform.",
    excerpt: "Demo excerpt — this article is placeholder content for design and development purposes only.",
    content: `<p>This is demo content for <strong>${title}</strong>.</p><p>The article body will be sourced from WordPress or Supabase when credentials are provided. Until then, this placeholder demonstrates layout, typography, and the reading experience.</p><h2>What we know</h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p><h2>Why it matters</h2><p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p><blockquote>“Pull quote from a fictional source for layout demonstration.”</blockquote><p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>`,
    featuredImage: artist.image,
    author: ["Mina K.", "Daniel R.", "Sora L.", "Jamie P."][i % 4],
    authorAvatar: grad("#ff6b9d", "#6b8eff", "A"),
    category: cat,
    tags: ["kpop", artist.slug, cat.toLowerCase()],
    relatedArtistIds: [artist.id],
    language: "en",
    source: "editorial",
    status: "published",
    viewCount: 5000 + i * 1234,
    commentCount: 24 + i * 7,
    reactionCount: 120 + i * 33,
    publishedAt: new Date(Date.now() - i * 86400000).toISOString(),
    modifiedAt: new Date(Date.now() - i * 80000000).toISOString(),
    readingTime: 3 + (i % 5),
  };
});

const usersData: User[] = [
  { id: "u_1", username: "demo_member", displayName: "Demo Member", email: "member@demo.test", role: "member", trustLevel: 1, points: 120, badges: ["b_1"], followedArtists: ["ar_1"], createdAt: new Date().toISOString(), avatar: grad("#ff6b9d", "#6b8eff", "M") },
  { id: "u_2", username: "demo_mod", displayName: "Demo Moderator", email: "mod@demo.test", role: "moderator", trustLevel: 4, points: 980, badges: ["b_1", "b_5"], followedArtists: ["ar_2"], createdAt: new Date().toISOString(), avatar: grad("#6bffb8", "#1f7d4a", "M") },
  { id: "u_3", username: "demo_editor", displayName: "Demo Editor", email: "editor@demo.test", role: "editor", trustLevel: 4, points: 1200, badges: ["b_1"], followedArtists: [], createdAt: new Date().toISOString(), avatar: grad("#ffd56b", "#ff6b6b", "E") },
  { id: "u_4", username: "demo_admin", displayName: "Demo Admin", email: "admin@demo.test", role: "admin", trustLevel: 5, points: 5000, badges: ["b_1", "b_5", "b_7"], followedArtists: ["ar_1", "ar_2"], createdAt: new Date().toISOString(), avatar: grad("#a06bff", "#ff6b9d", "A") },
  { id: "u_5", username: "fan_world", displayName: "FanWorld", email: "fan@demo.test", role: "trusted_member", trustLevel: 3, points: 540, badges: ["b_2"], followedArtists: ["ar_3"], createdAt: new Date().toISOString(), avatar: grad("#6bcfff", "#1f4a7d", "F") },
];

const threadsData: ForumThread[] = [
  ["AURORA BLOOM 'Stardust' MV reaction megathread", "cat_2", "ar_1", "Megathread", true],
  ["Predictions for the next wave of 5th gen groups", "cat_1", "ar_2", "Discussion"],
  ["LUNAR ECLIPSE world tour: ticket strategies", "cat_5", "ar_2", "Help"],
  ["Best album packaging of 2026 so far", "cat_6", "ar_3", "Discussion"],
  ["NEON TIDE choreography breakdown", "cat_1", "ar_3", "Analysis"],
  ["VELVET ROSES stylist appreciation thread", "cat_7", "ar_4", "Appreciation"],
  ["Weekly Discussion: What got you into K-pop?", "cat_1", "ar_5", "Weekly", true],
  ["KAIROS documentary spoiler thread (use spoiler tags)", "cat_3", "ar_1", "Spoiler"],
  ["MIDNIGHT ORBIT — is the comeback really happening?", "cat_3", "ar_3", "Rumor", false, false, false, true],
  ["Minji Park solo discography ranking", "cat_4", "ar_2", "Discussion"],
].map((row, i) => {
  const [title, categoryId, authorId, flair, pinned, locked, official, rumor] = row as any;
  return {
    id: `th_${i + 1}`,
    slug: (title as string).toLowerCase().replace(/[^a-z0-9]+/g, "-").slice(0, 60),
    categoryId,
    title,
    body: "Welcome to this discussion thread. This is demo seed content.",
    authorId,
    flair,
    pinned: !!pinned,
    locked: !!locked,
    official: !!official,
    rumor: !!rumor,
    views: 200 + i * 80,
    replies: 4 + i,
    reactions: 12 + i * 3,
    lastActivityAt: new Date(Date.now() - i * 3600000).toISOString(),
    createdAt: new Date(Date.now() - i * 86400000).toISOString(),
  };
});

const postsData: ForumPost[] = [];
threadsData.forEach((t) => {
  for (let i = 0; i < 4; i++) {
    postsData.push({
      id: `p_${t.id}_${i}`,
      threadId: t.id,
      authorId: usersData[i % usersData.length].id,
      body: i === 0
        ? "Great thread idea! I think the production direction this era is really refreshing."
        : "Adding to the discussion — the visual concept ties everything together for me.",
      reactions: 3 + i,
      createdAt: new Date(Date.now() - (i + 1) * 1800000).toISOString(),
    });
  }
});

const comebacksData: ComebackEvent[] = [
  ["ar_1", "AURORA BLOOM — Stardust EP", "album", 2],
  ["ar_2", "LUNAR ECLIPSE — Midnight MV", "mv", 5],
  ["ar_3", "NEON TIDE — World Tour Seoul", "concert", 10],
  ["ar_5", "Sora Han — Solo Single", "single", 14],
  ["ar_4", "MIDNIGHT ORBIT — Concept Teaser", "teaser", 20],
  ["ar_7", "POLAR LIGHTS — Debut Anniversary", "event", 30],
  ["ar_8", "KAIROS — Album Drop", "album", 35],
  ["ar_11", "SCARLET VOW — Member Birthday", "birthday", 45],
].map(([artistId, title, type, days], i) => ({
  id: `cb_${i + 1}`,
  artistId: artistId as string,
  title: title as string,
  type: type as ComebackEvent["type"],
  releaseAt: new Date(Date.now() + (days as number) * 86400000).toISOString(),
  description: "Fictional demo event.",
  image: artistsData.find((a) => a.id === artistId)?.image,
  threadId: i < threadsData.length ? threadsData[i].id : undefined,
}));

const pollsData: Poll[] = [
  { id: "po_1", slug: "best-comeback-q1", title: "Best comeback of Q1?", options: [{ id: "o1", label: "AURORA BLOOM", votes: 1240 }, { id: "o2", label: "LUNAR ECLIPSE", votes: 980 }, { id: "o3", label: "NEON TIDE", votes: 870 }, { id: "o4", label: "KAIROS", votes: 540 }], totalVotes: 3630 },
  { id: "po_2", slug: "next-world-tour", title: "Which group should tour next?", options: [{ id: "o1", label: "MIDNIGHT ORBIT", votes: 700 }, { id: "o2", label: "VELVET ROSES", votes: 620 }, { id: "o3", label: "POLAR LIGHTS", votes: 410 }], totalVotes: 1730 },
  { id: "po_3", slug: "song-of-week", title: "Song of the week?", options: [{ id: "o1", label: "Stardust", votes: 540 }, { id: "o2", label: "Midnight", votes: 380 }, { id: "o3", label: "Tide Pull", votes: 290 }], totalVotes: 1210 },
  { id: "po_4", slug: "favorite-era", title: "Favorite K-pop generation?", options: [{ id: "o1", label: "2nd gen", votes: 1100 }, { id: "o2", label: "3rd gen", votes: 1500 }, { id: "o3", label: "4th gen", votes: 1800 }, { id: "o4", label: "5th gen", votes: 950 }], totalVotes: 5350 },
  { id: "po_5", slug: "concept-style", title: "Best concept style?", options: [{ id: "o1", label: "Cyberpunk", votes: 410 }, { id: "o2", label: "Y2K", votes: 580 }, { id: "o3", label: "Ethereal", votes: 720 }], totalVotes: 1710 },
];

const communityWallData: CommunityPost[] = Array.from({ length: 10 }).map((_, i) => ({
  id: `cw_${i + 1}`,
  authorId: usersData[i % usersData.length].id,
  body: ["Streaming the new album on repeat 💿", "Counting down to the comeback ⏳", "Did anyone catch last night's live?", "Translation help needed for the fanmeet VOD 🙏", "Photo dump from the listening party!"][i % 5],
  language: ["en", "ko", "ja", "es", "id"][i % 5],
  reactions: 8 + i * 3,
  createdAt: new Date(Date.now() - i * 1200000).toISOString(),
  artistId: artistsData[i % artistsData.length].id,
}));

const badgesData: Badge[] = [
  { id: "b_1", name: "First Post", description: "Made your first contribution", icon: "✨", color: "#ff6b9d" },
  { id: "b_2", name: "Comeback Watcher", description: "Followed 5 comebacks", icon: "🎵", color: "#6b8eff" },
  { id: "b_3", name: "Translation Helper", description: "Helped translate posts", icon: "🌐", color: "#6bd4ff" },
  { id: "b_4", name: "Artist Expert", description: "Deep knowledge of an artist", icon: "🎤", color: "#a06bff" },
  { id: "b_5", name: "Trusted Fan", description: "Reached trust level 4", icon: "🛡️", color: "#6bffb8" },
  { id: "b_6", name: "Helpful Reporter", description: "Filed accurate reports", icon: "🚨", color: "#ff6b6b" },
  { id: "b_7", name: "Poll Voter", description: "Voted in 10 polls", icon: "🗳️", color: "#ffd56b" },
  { id: "b_8", name: "Community Builder", description: "Started discussions that grew", icon: "🌟", color: "#ff3060" },
];

const commentsData: Comment[] = articlesData.flatMap((a) =>
  Array.from({ length: 3 }).map((_, i) => ({
    id: `c_${a.id}_${i}`,
    articleId: a.id,
    authorId: usersData[i % usersData.length].id,
    body: ["Great write-up!", "Can't wait for the album drop.", "The concept photos are stunning."][i],
    reactions: 2 + i,
    createdAt: new Date(Date.now() - i * 600000).toISOString(),
  }))
);

const notificationsData: Notification[] = [
  { id: "n_1", userId: "u_1", type: "reply", body: "Demo Moderator replied to your thread", url: "/thread/aurora-bloom-stardust-mv-reaction-megathread", read: false, createdAt: new Date().toISOString() },
  { id: "n_2", userId: "u_1", type: "comeback", body: "AURORA BLOOM releases tomorrow", url: "/comebacks", read: false, createdAt: new Date().toISOString() },
  { id: "n_3", userId: "u_1", type: "follow", body: "FanWorld followed you", read: true, createdAt: new Date().toISOString() },
];

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
  videos: Array.from({ length: 8 }).map((_, i) => ({
    id: `v_${i + 1}`,
    title: `${artistsData[i % artistsData.length].name} — Performance Clip ${i + 1}`,
    artistId: artistsData[i % artistsData.length].id,
    category: ["MV", "Teaser", "Performance", "Interview", "Variety"][i % 5],
    thumbnail: artistsData[i % artistsData.length].image,
    duration: "3:24",
  })),
  charts: Array.from({ length: 10 }).map((_, i) => ({
    rank: i + 1,
    title: ["Stardust", "Midnight", "Tide Pull", "Orbit", "Solo Sun", "Velvet Rain", "Aurora", "Kairos", "Minji Rise", "Echo Tide"][i],
    artist: artistsData[i % artistsData.length].name,
    change: [+2, -1, 0, +5, -3, +1, 0, +4, -2, +3][i],
  })),
};

export type DemoData = typeof demoData;
