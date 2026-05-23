/**
 * Adapt AI-generated content (validated against the Zod schemas) into
 * the runtime types consumed by the UI. Keeps the AI schema decoupled
 * from internal IDs / display fields, so changing one never silently
 * breaks the other.
 */
import type {
  AiArticle, AiArtist, AiMember, AiComeback, AiForumThread,
} from "@/schemas/ai";
import type {
  Article, Artist, Member, ComebackEvent, ForumThread,
} from "@/types";

const grad = (a: string, b: string, label: string) =>
  `data:image/svg+xml;utf8,${encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 600'><defs><linearGradient id='g' x1='0' x2='1' y1='0' y2='1'><stop offset='0' stop-color='${a}'/><stop offset='1' stop-color='${b}'/></linearGradient></defs><rect width='800' height='600' fill='url(%23g)'/><text x='50%' y='50%' fill='white' font-family='sans-serif' font-size='44' font-weight='700' text-anchor='middle' dominant-baseline='middle' opacity='0.85'>${label}</text></svg>`
  )}`;

const slugToId = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, "_").slice(0, 60);

export function adaptArticle(a: AiArticle, idx: number, artistSlugToId: Map<string, string>): Article {
  return {
    id: `ai_art_${slugToId(a.slug)}_${idx}`,
    slug: a.slug,
    title: a.title,
    subtitle: a.subtitle,
    excerpt: a.excerpt,
    content: a.content,
    featuredImage: grad("#ff6b9d", "#6b8eff", a.title.slice(0, 18)),
    author: "AI Newsroom",
    authorAvatar: undefined,
    category: a.category,
    tags: a.tags,
    relatedArtistIds: a.relatedArtistSlugs.map((s) => artistSlugToId.get(s) ?? `ar_${slugToId(s)}`),
    language: a.language,
    source: "editorial",
    status: "published",
    viewCount: 1000 + idx * 137,
    commentCount: 5 + idx,
    reactionCount: 20 + idx * 3,
    publishedAt: new Date(Date.now() - idx * 86_400_000).toISOString(),
    modifiedAt: new Date().toISOString(),
    readingTime: a.readingTime,
  };
}

export function adaptArtist(a: AiArtist, idx: number): Artist {
  return {
    id: `ai_ar_${slugToId(a.slug)}`,
    slug: a.slug,
    name: a.name,
    koreanName: a.koreanName,
    type: a.type,
    agency: a.agency,
    debutDate: a.debutDate,
    fandomName: a.fandomName,
    generation: a.generation,
    status: a.status,
    nationality: a.nationality,
    bio: a.bio,
    image: grad("#a06bff", "#ff6b9d", a.name.slice(0, 14)),
    followerCount: 80_000 + idx * 12_345,
    memberIds: [],
    socialLinks: a.socialLinks,
  };
}

export function adaptMember(m: AiMember, artistSlugToId: Map<string, string>): Member {
  return {
    id: `ai_mb_${slugToId(m.slug)}`,
    slug: m.slug,
    stageName: m.stageName,
    fullName: m.fullName,
    koreanName: m.koreanName,
    birthday: m.birthday,
    nationality: m.nationality,
    groupId: artistSlugToId.get(m.groupSlug) ?? `ar_${slugToId(m.groupSlug)}`,
    position: m.position,
    mbti: m.mbti,
    image: grad("#6bd4ff", "#6b9dff", m.stageName.slice(0, 10)),
    facts: m.facts,
  };
}

export function adaptComeback(c: AiComeback, idx: number, artistSlugToId: Map<string, string>): ComebackEvent {
  return {
    id: `ai_cb_${idx}_${slugToId(c.title)}`,
    artistId: artistSlugToId.get(c.artistSlug) ?? `ar_${slugToId(c.artistSlug)}`,
    title: c.title,
    type: c.type,
    releaseAt: c.releaseAt,
    description: c.description,
  };
}

export function adaptForumThread(t: AiForumThread, idx: number): ForumThread {
  return {
    id: `ai_th_${slugToId(t.slug)}`,
    slug: t.slug,
    categoryId: `cat_${slugToId(t.categorySlug)}`,
    title: t.title,
    body: t.body,
    authorId: "u_ai_newsroom",
    flair: t.flair,
    rumor: t.rumor,
    views: 100 + idx * 17,
    replies: 3 + idx,
    reactions: 10 + idx * 2,
    lastActivityAt: new Date(Date.now() - idx * 3_600_000).toISOString(),
    createdAt: new Date(Date.now() - idx * 7_200_000).toISOString(),
  };
}
