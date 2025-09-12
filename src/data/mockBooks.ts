import type { Book } from '../types';

export const mockBooks: Book[] = [
  {
    id: '1',
    title: 'Introduction to Computer Science',
    author: 'Dr. Jane Smith',
    subject: 'Computer Science',
    description: 'A comprehensive introduction to the fundamentals of computer science, covering algorithms, data structures, and programming concepts.',
    coverImage: '/api/placeholder/300/400',
    pdfUrl: '/books/intro-cs.pdf',
    createdAt: '2024-01-15T08:00:00Z',
    updatedAt: '2024-01-15T08:00:00Z'
  },
  {
    id: '2',
    title: 'Advanced Mathematics',
    author: 'Prof. Michael Johnson',
    subject: 'Mathematics',
    description: 'Advanced mathematical concepts including calculus, linear algebra, and differential equations.',
    coverImage: '/api/placeholder/300/400',
    pdfUrl: '/books/advanced-math.pdf',
    createdAt: '2024-01-20T10:30:00Z',
    updatedAt: '2024-01-20T10:30:00Z'
  },
  {
    id: '3',
    title: 'Physics Fundamentals',
    author: 'Dr. Sarah Wilson',
    subject: 'Physics',
    description: 'Core principles of physics covering mechanics, thermodynamics, and electromagnetism.',
    coverImage: '/api/placeholder/300/400',
    pdfUrl: '/books/physics-fundamentals.pdf',
    createdAt: '2024-02-01T14:15:00Z',
    updatedAt: '2024-02-01T14:15:00Z'
  },
  {
    id: '4',
    title: 'Organic Chemistry',
    author: 'Prof. David Brown',
    subject: 'Chemistry',
    description: 'Comprehensive guide to organic chemistry, including molecular structure and reaction mechanisms.',
    coverImage: '/api/placeholder/300/400',
    pdfUrl: '/books/organic-chemistry.pdf',
    createdAt: '2024-02-10T09:45:00Z',
    updatedAt: '2024-02-10T09:45:00Z'
  },
  {
    id: '5',
    title: 'World History: Modern Era',
    author: 'Dr. Emily Davis',
    subject: 'History',
    description: 'Exploration of world history from the Renaissance to the modern day.',
    coverImage: '/api/placeholder/300/400',
    pdfUrl: '/books/world-history.pdf',
    createdAt: '2024-02-15T16:20:00Z',
    updatedAt: '2024-02-15T16:20:00Z'
  },
  {
    id: '6',
    title: 'English Literature Anthology',
    author: 'Prof. Robert Miller',
    subject: 'Literature',
    description: 'Collection of classic and contemporary English literature with analysis and commentary.',
    coverImage: '/api/placeholder/300/400',
    pdfUrl: '/books/english-lit.pdf',
    createdAt: '2024-02-20T11:00:00Z',
    updatedAt: '2024-02-20T11:00:00Z'
  }
];

export const getBooksBySubject = (subject: string): Book[] => {
  return mockBooks.filter(book => 
    book.subject.toLowerCase().includes(subject.toLowerCase())
  );
};

export const searchBooks = (query: string): Book[] => {
  const lowercaseQuery = query.toLowerCase();
  return mockBooks.filter(book => 
    book.title.toLowerCase().includes(lowercaseQuery) ||
    book.author.toLowerCase().includes(lowercaseQuery) ||
    book.subject.toLowerCase().includes(lowercaseQuery) ||
    book.description?.toLowerCase().includes(lowercaseQuery)
  );
};

export const getBookById = (id: string): Book | undefined => {
  return mockBooks.find(book => book.id === id);
};