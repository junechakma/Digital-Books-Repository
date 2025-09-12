import React, { useState, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Input, Modal, useToast } from '../../components/ui';
import { mockBooks } from '../../data/mockBooks';
import type { Book } from '../../types';
import { formatDate } from '../../utils';

export const BooksManagement: React.FC = () => {
  const [books, setBooks] = useState<Book[]>(mockBooks);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSubject, setSelectedSubject] = useState('');
  const [selectedBooks, setSelectedBooks] = useState<Set<string>>(new Set());
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [bookToDelete, setBookToDelete] = useState<Book | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const { addToast } = useToast();
  const navigate = useNavigate();

  // Get unique subjects
  const subjects = useMemo(() => {
    const allSubjects = books.map(book => book.subject);
    return ['All', ...Array.from(new Set(allSubjects))];
  }, [books]);

  // Filter books
  const filteredBooks = useMemo(() => {
    let filtered = books;

    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(book =>
        book.title.toLowerCase().includes(query) ||
        book.author.toLowerCase().includes(query) ||
        book.subject.toLowerCase().includes(query)
      );
    }

    if (selectedSubject && selectedSubject !== 'All') {
      filtered = filtered.filter(book => book.subject === selectedSubject);
    }

    return filtered;
  }, [books, searchQuery, selectedSubject]);

  const handleSelectBook = (bookId: string) => {
    const newSelected = new Set(selectedBooks);
    if (newSelected.has(bookId)) {
      newSelected.delete(bookId);
    } else {
      newSelected.add(bookId);
    }
    setSelectedBooks(newSelected);
  };

  const handleSelectAll = () => {
    if (selectedBooks.size === filteredBooks.length) {
      setSelectedBooks(new Set());
    } else {
      setSelectedBooks(new Set(filteredBooks.map(book => book.id)));
    }
  };

  const handleEdit = (bookId: string) => {
    navigate(`/admin/books/edit/${bookId}`);
  };

  const handleDelete = (book: Book) => {
    setBookToDelete(book);
    setShowDeleteModal(true);
  };

  const confirmDelete = async () => {
    if (!bookToDelete) return;

    setIsLoading(true);
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000));

    setBooks(prev => prev.filter(book => book.id !== bookToDelete.id));
    setShowDeleteModal(false);
    setBookToDelete(null);
    setIsLoading(false);

    addToast({
      type: 'success',
      title: 'Book deleted',
      message: `"${bookToDelete.title}" has been deleted successfully.`
    });
  };

  const handleBulkDelete = async () => {
    if (selectedBooks.size === 0) return;

    setIsLoading(true);
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500));

    setBooks(prev => prev.filter(book => !selectedBooks.has(book.id)));
    setSelectedBooks(new Set());
    setIsLoading(false);

    addToast({
      type: 'success',
      title: 'Books deleted',
      message: `${selectedBooks.size} books have been deleted successfully.`
    });
  };

  const handleClearFilters = () => {
    setSearchQuery('');
    setSelectedSubject('');
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Books Management</h1>
          <p className="mt-1 text-sm text-gray-600">
            Manage your digital book collection
          </p>
        </div>
        <div className="mt-4 sm:mt-0 flex space-x-3">
          <Link to="/admin/books/new">
            <Button>
              <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              Add Book
            </Button>
          </Link>
          {selectedBooks.size > 0 && (
            <Button
              variant="danger"
              onClick={handleBulkDelete}
              isLoading={isLoading}
            >
              Delete Selected ({selectedBooks.size})
            </Button>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow p-6 mb-6">
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
          
          <div className="flex gap-2">
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
      <div className="flex items-center justify-between mb-4">
        <p className="text-sm text-gray-600">
          {filteredBooks.length} of {books.length} books
        </p>
        {filteredBooks.length > 0 && (
          <div className="flex items-center space-x-2">
            <input
              type="checkbox"
              checked={selectedBooks.size === filteredBooks.length && filteredBooks.length > 0}
              onChange={handleSelectAll}
              className="rounded border-gray-300 text-[#ca1d26] focus:ring-[#ca1d26]"
            />
            <label className="text-sm text-gray-600">Select all</label>
          </div>
        )}
      </div>

      {/* Books Table */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input
                    type="checkbox"
                    checked={selectedBooks.size === filteredBooks.length && filteredBooks.length > 0}
                    onChange={handleSelectAll}
                    className="rounded border-gray-300 text-[#ca1d26] focus:ring-[#ca1d26]"
                  />
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Book
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Author
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Subject
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Added
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredBooks.map((book) => (
                <tr key={book.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <input
                      type="checkbox"
                      checked={selectedBooks.has(book.id)}
                      onChange={() => handleSelectBook(book.id)}
                      className="rounded border-gray-300 text-[#ca1d26] focus:ring-[#ca1d26]"
                    />
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center">
                      <div className="flex-shrink-0 h-12 w-8">
                        <div className="h-12 w-8 bg-gray-200 rounded flex items-center justify-center">
                          <svg className="h-6 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                          </svg>
                        </div>
                      </div>
                      <div className="ml-4">
                        <div className="text-sm font-medium text-gray-900">{book.title}</div>
                        {book.description && (
                          <div className="text-sm text-gray-500 truncate max-w-xs">
                            {book.description}
                          </div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {book.author}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                      {book.subject}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(book.createdAt)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => handleEdit(book.id)}
                    >
                      Edit
                    </Button>
                    <Button
                      size="sm"
                      variant="danger"
                      onClick={() => handleDelete(book)}
                    >
                      Delete
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {filteredBooks.length === 0 && (
          <div className="text-center py-12">
            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <h3 className="mt-4 text-lg font-medium text-gray-900">No books found</h3>
            <p className="mt-2 text-gray-500">Try adjusting your search or filter criteria.</p>
            <div className="mt-6">
              <Button onClick={handleClearFilters}>
                Clear Filters
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        title="Delete Book"
      >
        <div className="space-y-4">
          <p className="text-gray-600">
            Are you sure you want to delete "{bookToDelete?.title}"? This action cannot be undone.
          </p>
          <div className="flex justify-end space-x-3">
            <Button
              variant="outline"
              onClick={() => setShowDeleteModal(false)}
              disabled={isLoading}
            >
              Cancel
            </Button>
            <Button
              variant="danger"
              onClick={confirmDelete}
              isLoading={isLoading}
            >
              Delete Book
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};