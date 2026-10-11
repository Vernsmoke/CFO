"use client";
import { useEffect, useSyncExternalStore } from "react";
import { Moon, Sun } from "lucide-react";

const eventName = "cfo-preferences";
function subscribe(callback: () => void) {
  window.addEventListener("storage", callback);
  window.addEventListener(eventName, callback);
  return () => {
    window.removeEventListener("storage", callback);
    window.removeEventListener(eventName, callback);
  };
}
export function usePreference(key: string, fallback: string) {
  const value = useSyncExternalStore(
    subscribe,
    () => {
      try {
        return localStorage.getItem(`cfo-${key}`) ?? fallback;
      } catch {
        return fallback;
      }
    },
    () => fallback,
  );
  const setValue = (next: string) => {
    try {
      localStorage.setItem(`cfo-${key}`, next);
    } catch {
      /* Preferences are optional. */
    }
    window.dispatchEvent(new Event(eventName));
  };
  return [value, setValue] as const;
}
export function ThemeControl() {
  const [theme, setTheme] = usePreference("theme", "light");
  useEffect(() => {
    document.documentElement.dataset.theme = theme;
  }, [theme]);
  return (
    <button
      type="button"
      className="theme-control"
      aria-label={`Switch to ${theme === "dark" ? "light" : "dark"} mode`}
      onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
    >
      {theme === "dark" ? <Sun size={18} /> : <Moon size={18} />}
      <span>{theme === "dark" ? "Light mode" : "Dark mode"}</span>
    </button>
  );
}
