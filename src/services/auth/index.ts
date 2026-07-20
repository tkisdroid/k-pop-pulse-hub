import { demoAuthProvider, type AuthProvider } from "./demoAuthProvider";
import { supabaseAuthProvider } from "./supabaseAuthProvider";
import { wordpressAuthProvider } from "./wordpressAuthProvider";
import { supabaseConfigured } from "@/lib/supabaseClient";

const wpUrl = (import.meta as any).env?.VITE_WORDPRESS_API_URL as string | undefined;
// When mounted inside WordPress, the plugin injects window.kpopblogConfig.apiUrl.
const insideWordPress =
  typeof window !== "undefined" && !!(window as any).kpopblogConfig?.apiUrl;

export const authProvider: AuthProvider =
  insideWordPress || wpUrl
    ? wordpressAuthProvider
    : supabaseConfigured
      ? supabaseAuthProvider
      : demoAuthProvider;
export type { AuthProvider };
