/**
 * The signed-in user as shared with every page by HandleInertiaRequests. Kept to
 * the fields the UI actually reads — widen both sides together, never just this.
 */
type AuthUser = {
    id: number;
    name: string;
    email: string;
};

export type Auth = {
    user: AuthUser | null;
};
