import type { AuthProvider } from "./demoAuthProvider";
// Placeholder. Activated later when Supabase is connected.
export const supabaseAuthProvider: AuthProvider = {
  name: "supabase",
  getCurrentUser: () => null,
  async signIn() {
    throw new Error("Supabase auth not configured");
  },
  async signInDemo() {
    throw new Error("Supabase auth not configured");
  },
  async signUp() {
    throw new Error("Supabase auth not configured");
  },
  async signInWithProvider(p) {
    return { pending: true, message: `${p} OAuth requires Supabase credentials.` };
  },
  async signOut() {},
  onChange() {
    return () => {};
  },
};
