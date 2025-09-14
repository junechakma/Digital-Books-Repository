import React, { useState, useEffect, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Input, Button, LoadingPage } from '../../components/ui';
import { BookCard } from '../../components/ui/BookCard';
import { books, searchBooks } from '../../data/mockBooks';
import { useDebounce } from '../../hooks/useDebounce';

export const BooksPage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchQuery, setSearchQuery] = useState(searchParams.get('search') || '');
  const [selectedSubject, setSelectedSubject] = useState('');
  const [isLoading] = useState(false);
  
  const debouncedSearchQuery = useDebounce(searchQuery, 300);

  // Get unique subjects
  const subjects = useMemo(() => {
    const allSubjects = books.map(book => book.subject);
    return ['All', ...Array.from(new Set(allSubjects))];
  }, []);

  // Filter books based on search and subject
  const filteredBooks = useMemo(() => {
    let filteredBooks = books;

    if (debouncedSearchQuery.trim()) {
      filteredBooks = searchBooks(debouncedSearchQuery);
    }

    if (selectedSubject && selectedSubject !== 'All') {
      filteredBooks = filteredBooks.filter(book => book.subject === selectedSubject);
    }

    return filteredBooks;
  }, [debouncedSearchQuery, selectedSubject]);

  // Update URL when search changes
  useEffect(() => {
    const params = new URLSearchParams();
    if (debouncedSearchQuery.trim()) {
      params.set('search', debouncedSearchQuery.trim());
    }
    if (selectedSubject && selectedSubject !== 'All') {
      params.set('subject', selectedSubject);
    }
    setSearchParams(params);
  }, [debouncedSearchQuery, selectedSubject, setSearchParams]);

  // Load from URL params on mount
  useEffect(() => {
    const search = searchParams.get('search');
    const subject = searchParams.get('subject');
    
    if (search) setSearchQuery(search);
    if (subject) setSelectedSubject(subject);
  }, []);

  const handleClearFilters = () => {
    setSearchQuery('');
    setSelectedSubject('');
    setSearchParams(new URLSearchParams());
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Book Collection</h1>
          <p className="text-gray-600">
            Browse and search our extensive collection of academic books
          </p>
        </div>

        {/* Search and Filters */}
        <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="md:col-span-2">
              <Input
                type="text"
                placeholder="Search books, authors, subjects..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full"
              />
            </div>
            
            <div className="flex flex-col sm:flex-row gap-2">
              <select
                value={selectedSubject}
                onChange={(e) => setSelectedSubject(e.target.value)}
                className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26] bg-white"
              >
                {subjects.map(subject => (
                  <option key={subject} value={subject === 'All' ? '' : subject}>
                    {subject}
                  </option>
                ))}
              </select>
              
              {(searchQuery || selectedSubject) && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleClearFilters}
                  className="whitespace-nowrap"
                >
                  Clear
                </Button>
              )}
            </div>
          </div>
        </div>

        {/* Results Summary */}
        <div className="flex items-center justify-between mb-6">
          <p className="text-gray-600">
            {filteredBooks.length} {filteredBooks.length === 1 ? 'book' : 'books'} found
            {debouncedSearchQuery && (
              <span> for "{debouncedSearchQuery}"</span>
            )}
            {selectedSubject && selectedSubject !== 'All' && (
              <span> in {selectedSubject}</span>
            )}
          </p>
        </div>

        {/* Books Grid */}
        {isLoading ? (
          <LoadingPage message="Loading books..." />
        ) : filteredBooks.length === 0 ? (
          <div className="text-center py-16">
            <svg className="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <h3 className="text-lg font-medium text-gray-900 mb-2">No books found</h3>
            <p className="text-gray-500 mb-4">
              Try adjusting your search terms or filters.
            </p>
            <Button onClick={handleClearFilters}>
              Clear Filters
            </Button>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-6">
            {filteredBooks.map((book) => (
              <BookCard
                key={book.id}
                book={book}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
};