const endpoint = (import.meta as any).env?.VITE_TRANSLATION_API_ENDPOINT as string | undefined;
export const translationProvider = {
  name: endpoint ? "api" : "demo",
  configured: Boolean(endpoint),
  async translate(text: string, targetLang: string): Promise<{ text: string; sourceLang: string; machine: boolean }> {
    if (!endpoint) {
      return { text: `[${targetLang}] ${text}`, sourceLang: "en", machine: true };
    }
    // Future: POST to endpoint
    return { text, sourceLang: "auto", machine: true };
  },
};
