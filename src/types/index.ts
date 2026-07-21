export type UserRole =
  "guest" | "member" | "contributor" | "trusted_member" | "moderator" | "editor" | "admin";

export interface UserCapabilities {
  moderateCommunity: boolean;
  manageAutomation: boolean;
  manageNotifications: boolean;
  manageAds: boolean;
  manageOptions: boolean;
}

export interface User {
  id: string;
  username: string;
  displayName: string;
  email: string;
  avatar?: string;
  bio?: string;
  country?: string;
  language?: string;
  role: UserRole;
  trustLevel: number;
  points: number;
  badges: string[];
  followedArtists: string[];
  createdAt: string;
  capabilities?: UserCapabilities;
}

export type PublicProfile = Omit<User, "email" | "capabilities">;

export interface PublicAuthor {
  id: string;
  username: string;
  displayName: string;
  avatar?: string;
}

export interface Article {
  id: string;
  wpId?: number;
  slug: string;
  title: string;
  subtitle?: string;
  excerpt: string;
  content: string;
  featuredImage: string;
  author: string;
  authorAvatar?: string;
  category: string;
  tags: string[];
  relatedArtistIds: string[];
  language: string;
  source: "editorial" | "wordpress" | "wire" | "user" | "ai-grounded";
  status: "draft" | "published" | "archived";
  viewCount: number;
  commentCount: number;
  reactionCount: number;
  publishedAt: string;
  modifiedAt: string;
  readingTime: number;
}

export interface Artist {
  id: string;
  slug: string;
  name: string;
  koreanName?: string;
  type: "boy_group" | "girl_group" | "soloist" | "coed" | "band";
  agency: string;
  debutDate: string;
  fandomName?: string;
  generation: 1 | 2 | 3 | 4 | 5;
  status: "active" | "hiatus" | "disbanded" | "pre_debut";
  nationality: string;
  bio: string;
  image: string;
  followerCount: number;
  memberIds: string[];
  socialLinks?: Record<string, string>;
}

export interface Member {
  id: string;
  slug: string;
  stageName: string;
  fullName: string;
  koreanName?: string;
  birthday: string;
  nationality: string;
  groupId: string;
  position: string[];
  mbti?: string;
  image: string;
  facts: string[];
}

export interface ForumCategory {
  id: string;
  slug: string;
  name: string;
  description: string;
  icon: string;
  threadCount: number;
  postCount: number;
  rules?: string[];
}

export interface ForumThread {
  id: string;
  slug: string;
  categoryId: string;
  title: string;
  body: string;
  authorId: string;
  author?: PublicAuthor;
  flair?: string;
  pinned?: boolean;
  locked?: boolean;
  official?: boolean;
  rumor?: boolean;
  views: number;
  replies: number;
  reactions: number;
  lastActivityAt: string;
  createdAt: string;
  status?: "publish" | "pending" | "draft" | "private";
}

export interface ForumPost {
  id: string;
  threadId: string;
  parentId?: string;
  authorId: string;
  author?: PublicAuthor;
  body: string;
  reactions: number;
  createdAt: string;
  editedAt?: string;
  status?: "published" | "pending";
}

export interface ComebackEvent {
  id: string;
  artistId: string;
  title: string;
  type: "album" | "single" | "mv" | "teaser" | "concert" | "debut" | "birthday" | "event";
  releaseAt: string;
  description?: string;
  image?: string;
  threadId?: string;
}

export interface Poll {
  id: string;
  slug: string;
  title: string;
  description?: string;
  options: { id: string; label: string; votes: number }[];
  totalVotes: number;
  endsAt?: string;
  artistId?: string;
}

export interface CommunityPost {
  id: string;
  authorId: string;
  author?: PublicAuthor;
  body: string;
  language: string;
  reactions: number;
  createdAt: string;
  artistId?: string;
  status?: "publish" | "pending" | "draft" | "private";
}

export interface Comment {
  id: string;
  articleId: string;
  authorId: string;
  body: string;
  parentId?: string;
  reactions: number;
  createdAt: string;
}

export interface Notification {
  id: string;
  userId: string;
  type: "reply" | "mention" | "comeback" | "follow" | "system";
  body: string;
  url?: string;
  read: boolean;
  createdAt: string;
}

export interface Badge {
  id: string;
  name: string;
  description: string;
  icon: string;
  color: string;
}

export interface Report {
  id: string;
  targetType: "article" | "thread" | "community" | "post" | "reply" | "comment" | "user";
  targetId: string;
  reporterId: string;
  reason: string;
  status: "pending" | "resolved" | "dismissed";
  createdAt: string;
  resolutionNote?: string;
  resolvedBy?: string;
  resolvedAt?: string | null;
}
