import { demoCommunityProvider } from "./demoCommunityProvider";
import { wordpressCommunityProvider } from "./wordpressCommunityProvider";

const configuredWordPress = (import.meta as { env?: { VITE_WORDPRESS_API_URL?: string } }).env
  ?.VITE_WORDPRESS_API_URL;
const insideWordPress = typeof window !== "undefined" && Boolean(window.kpopblogConfig?.apiUrl);

export const communityProvider =
  insideWordPress || configuredWordPress ? wordpressCommunityProvider : demoCommunityProvider;

export type { CommunityProvider, Paginated } from "./types";
