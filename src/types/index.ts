export interface Book {
  id: string;
  title: string;
  author: string;
  subject: string;
  description?: string;
  coverImage?: string;
  pdfUrl?: string;
  createdAt: string;
  updatedAt: string;
}

export interface CartItem {
  bookId: string;
  book: Book;
  addedAt: string;
}

export interface User {
  id: string;
  email: string;
  isAdmin: boolean;
}

export interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

export interface CartState {
  items: CartItem[];
  total: number;
}

export interface AdminStats {
  totalBooks: number;
  totalDownloads: number;
  recentActivity: ActivityItem[];
}

export interface ActivityItem {
  id: string;
  type: 'upload' | 'download' | 'delete';
  description: string;
  timestamp: string;
}

export interface SearchFilters {
  query: string;
  subject: string;
  author: string;
}