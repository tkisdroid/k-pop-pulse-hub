import { demoAuthProvider, type AuthProvider } from "./demoAuthProvider";
import { supabaseAuthProvider } from "./supabaseAuthProvider";
import { supabaseConfigured } from "@/lib/supabaseClient";

export const authProvider: AuthProvider = supabaseConfigured ? supabaseAuthProvider : demoAuthProvider;
export type { AuthProvider };
