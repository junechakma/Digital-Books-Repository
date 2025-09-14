import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Input, Button } from '../../components/ui';
import { useDebounce } from '../../hooks/useDebounce';
import { searchBooks } from '../../data/mockBooks';
import type { Book } from '../../types';

export const HomePage: React.FC = () => {
  const [searchQuery, setSearchQuery] = useState('');
  const [suggestions, setSuggestions] = useState<Book[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const debouncedSearchQuery = useDebounce(searchQuery, 300);
  const navigate = useNavigate();

  useEffect(() => {
    if (debouncedSearchQuery.trim().length > 0) {
      const results = searchBooks(debouncedSearchQuery).slice(0, 5);
      setSuggestions(results);
      setShowSuggestions(true);
    } else {
      setSuggestions([]);
      setShowSuggestions(false);
    }
  }, [debouncedSearchQuery]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/books?search=${encodeURIComponent(searchQuery.trim())}`);
    }
  };

  const handleSuggestionClick = (bookId: string) => {
    setShowSuggestions(false);
    setSearchQuery('');
    navigate(`/books/${bookId}`);
  };

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <div className="relative bg-gradient-to-r from-[#ca1d26] to-[#b01822] text-white overflow-visible">
        {/* Background Image */}
        <div 
          className="absolute inset-0 bg-cover bg-center bg-no-repeat opacity-20"
          style={{
            backgroundImage: 'url(/hero-bg.jpg)',
          }}
        />
        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
          <div className="text-center">
            <h1 className="text-4xl md:text-6xl font-bold mb-6">
              Digital Books Wallet
            </h1>
            <p className="text-xl md:text-2xl mb-12 max-w-3xl mx-auto">
              Access thousands of academic books and resources. 
              Search, download, and expand your knowledge.
            </p>

            {/* Search Bar */}
            <div className="max-w-2xl mx-auto relative z-[100] px-4 sm:px-0">
              <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-2 sm:gap-2">
                <div className="flex-1 relative">
                  <Input
                    type="text"
                    placeholder="Search for books, authors, subjects..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="text-base sm:text-lg py-3 sm:py-4 px-4 sm:px-6 pr-12 bg-white text-gray-900 w-full"
                    onFocus={() => setShowSuggestions(suggestions.length > 0)}
                    onBlur={() => setTimeout(() => setShowSuggestions(false), 200)}
                  />
                  {/* Search Icon */}
                  {searchQuery && (
                    <button
                      type="submit"
                      className="absolute right-3 top-1/2 transform -translate-y-1/2 text-[#ca1d26] hover:text-[#b01822] transition-colors p-1"
                    >
                      <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </button>
                  )}
                  
                  {/* Search Suggestions */}
                  {showSuggestions && suggestions.length > 0 && (
                    <div className="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-md shadow-lg z-[100] mt-1">
                      {suggestions.map((book) => (
                        <button
                          key={book.id}
                          className="w-full text-left px-4 py-3 hover:bg-gray-50 border-b last:border-b-0 text-gray-900"
                          onClick={() => handleSuggestionClick(book.id)}
                        >
                          <div className="font-medium">{book.title}</div>
                          <div className="text-sm text-gray-600">
                            {book.author} • {book.subject}
                          </div>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
                <Button
                  type="submit"
                  size="lg"
                  className="hidden sm:flex px-6 sm:px-8 py-3 sm:py-4 text-[#ca1d26] font-semibold border-2 border-gray-100 hover:border-gray-300 shadow-lg w-full sm:w-auto"
                >
                  Search
                </Button>
              </form>
            </div>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="py-16 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 mb-4">
              Why Choose Our Repository?
            </h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              Access a vast collection of academic resources with ease
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
        <div className="text-center">
              <div className="w-16 h-16 bg-[#ca1d26] rounded-full flex items-center justify-center mx-auto mb-4">
                <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold mb-2">Vast Collection</h3>
              <p className="text-gray-600">
                Access thousands of academic books across multiple subjects and disciplines.
              </p>
            </div>

            <div className="text-center">
              <div className="w-16 h-16 bg-[#ca1d26] rounded-full flex items-center justify-center mx-auto mb-4">
                <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold mb-2">Easy Search</h3>
              <p className="text-gray-600">
                Find exactly what you need with our powerful search and filtering capabilities.
              </p>
            </div>

            <div className="text-center">
              <div className="w-16 h-16 bg-[#ca1d26] rounded-full flex items-center justify-center mx-auto mb-4">
                <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold mb-2">Instant Download</h3>
              <p className="text-gray-600">
                Download books instantly after verification with your university email.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* CTA Section */}
      <div className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold text-gray-900 mb-4">
            Ready to Start Learning?
          </h2>
          <p className="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
            Browse our extensive collection of academic books and resources
          </p>
          <Link to="/books">
            <Button size="lg" className="px-8 py-4">
              Browse Books
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
};