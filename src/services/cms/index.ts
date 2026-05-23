import { demoCmsProvider } from "./demoProvider";
import { wordpressCmsProvider } from "./wordpressProvider";
import { supabaseCmsProvider } from "./supabaseProvider";
import type { CmsProvider } from "./types";

const wpUrl = (import.meta as any).env?.VITE_WORDPRESS_API_URL as string | undefined;
const supaUrl = (import.meta as any).env?.VITE_SUPABASE_URL as string | undefined;
// When mounted inside WordPress, the plugin injects window.kpopblogConfig.apiUrl.
const insideWordPress =
  typeof window !== "undefined" && !!(window as any).kpopblogConfig?.apiUrl;

export const cmsProvider: CmsProvider =
  insideWordPress || wpUrl
    ? wordpressCmsProvider
    : supaUrl
      ? supabaseCmsProvider
      : demoCmsProvider;

export type { CmsProvider };
