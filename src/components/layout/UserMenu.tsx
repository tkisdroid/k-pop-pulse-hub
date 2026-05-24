import { useState } from "react";
import { Link } from "@tanstack/react-router";
import { useAuth } from "@/hooks/useAuth";

export function UserMenu() {
  const { user, signOut } = useAuth();
  const [open, setOpen] = useState(false);
  if (!user) return null;
  return (
    <div className="relative">
      <button onClick={() => setOpen((o) => !o)} className="size-9 rounded-full overflow-hidden border border-border">
        {user.avatar ? <img src={user.avatar} alt="" className="size-full object-cover" /> : <div className="size-full gradient-neon" />}
      </button>
      {open && (
        <div className="absolute right-0 mt-2 w-56 bg-popover border border-border rounded-lg shadow-lg z-50 overflow-hidden">
          <div className="px-3 py-2 border-b border-border">
            <div className="text-sm font-medium">{user.displayName}</div>
            <div className="text-xs text-muted-foreground">@{user.username} · {user.role}</div>
          </div>
          <Link to="/profile/$username" params={{ username: user.username }} onClick={() => setOpen(false)} className="block px-3 py-2 text-sm hover:bg-accent">My profile</Link>
          <Link to="/bookmarks" onClick={() => setOpen(false)} className="block px-3 py-2 text-sm hover:bg-accent">Saved articles</Link>
          {(user.role === "admin") && <Link to="/admin" onClick={() => setOpen(false)} className="block px-3 py-2 text-sm hover:bg-accent">Admin dashboard</Link>}
          {(user.role === "admin" || user.role === "moderator") && <Link to="/moderation" onClick={() => setOpen(false)} className="block px-3 py-2 text-sm hover:bg-accent">Moderation</Link>}
          <button onClick={() => { signOut(); setOpen(false); }} className="w-full text-left px-3 py-2 text-sm hover:bg-accent border-t border-border">Log out</button>
        </div>
      )}
    </div>
  );
}
