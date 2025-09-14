import type { Book } from '../types';
import { generateBooksFromPDFs, getBooksBySubject as filterBySubject, searchBooks as filterBooks, getBookById as findBookById } from '../utils/pdfBookGenerator';

// Generate books from actual PDF files
export const books: Book[] = generateBooksFromPDFs();

// Re-export utility functions
export const getBooksBySubject = (subject: string): Book[] => {
  return filterBySubject(books, subject);
};

export const searchBooks = (query: string): Book[] => {
  return filterBooks(books, query);
};

export const getBookById = (id: string): Book | undefined => {
  return findBookById(books, id);
};

// For backward compatibility, export as mockBooks as well
export const mockBooks = books;