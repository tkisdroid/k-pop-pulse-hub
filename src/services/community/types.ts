import type {
  CommunityPost,
  ForumCategory,
  ForumPost,
  ForumThread,
  PublicProfile,
  Report,
  User,
} from "@/types";

export interface Paginated<T> {
  items: T[];
  total: number;
  totalPages: number;
}

export interface CommunityProvider {
  name: "wordpress" | "demo";
  listCommunity(input?: {
    mine?: boolean;
    page?: number;
    perPage?: number;
  }): Promise<Paginated<CommunityPost>>;
  createCommunity(input: {
    body: string;
    language?: string;
  }): Promise<{ item: CommunityPost; status: string }>;
  createSubmission(input: {
    type: string;
    subject: string;
    details: string;
  }): Promise<{ id: string; status: string }>;
  listCategories(): Promise<ForumCategory[]>;
  listThreads(input?: {
    category?: string;
    page?: number;
    perPage?: number;
  }): Promise<Paginated<ForumThread>>;
  getThread(slug: string): Promise<ForumThread | null>;
  createThread(input: {
    categorySlug: string;
    title: string;
    body: string;
    language?: string;
  }): Promise<{ item: ForumThread; status: string }>;
  listReplies(slug: string, page?: number): Promise<Paginated<ForumPost>>;
  createReply(
    slug: string,
    body: string,
    parentId?: string,
  ): Promise<{ item: ForumPost; pending: boolean }>;
  getProfile(username: string): Promise<PublicProfile | null>;
  updateProfile(input: {
    displayName: string;
    bio?: string;
    country?: string;
    language?: string;
  }): Promise<User>;
  report(input: {
    targetType: Report["targetType"];
    targetId: string;
    reason: string;
  }): Promise<Report>;
  listReports(page?: number): Promise<Paginated<Report>>;
  resolveReport(id: string, action: "resolved" | "dismissed", note?: string): Promise<Report>;
}
