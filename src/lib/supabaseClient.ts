// Supabase-ready client. Safe to import even without credentials.
// Real init happens later when VITE_SUPABASE_URL and VITE_SUPABASE_ANON_KEY exist.

const url = (import.meta as any).env?.VITE_SUPABASE_URL as string | undefined;
const key = (import.meta as any).env?.VITE_SUPABASE_ANON_KEY as string | undefined;

export const supabaseConfigured = Boolean(url && key);

// We export a null placeholder; real client wires later via dynamic import.
export const supabase: null = null;

export function getSupabaseConfig() {
  return { url, key, configured: supabaseConfigured };
}
