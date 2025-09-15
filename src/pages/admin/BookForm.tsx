import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Button, Input, useToast } from '../../components/ui';
import { getBookById } from '../../data/mockBooks';
import type { Book } from '../../types';

interface BookFormData {
  title: string;
  author: string;
  subject: string;
  description: string;
  coverImage: string;
  pdfUrl: string;
  edition: string;
  publicationDate: string;
  publisher: string;
  isbn: string;
  bookHash: string;
  source: string;
}

export const BookForm: React.FC = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { addToast } = useToast();
  const isEditing = Boolean(id);

  const [formData, setFormData] = useState<BookFormData>({
    title: '',
    author: '',
    subject: '',
    description: '',
    coverImage: '',
    pdfUrl: '',
    edition: '',
    publicationDate: '',
    publisher: '',
    isbn: '',
    bookHash: '',
    source: ''
  });
  
  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Partial<BookFormData>>({});

  // Common subjects for dropdown
  const subjects = [
    'Computer Science',
    'Mathematics', 
    'Physics',
    'Chemistry',
    'Biology',
    'History',
    'Literature',
    'Psychology',
    'Economics',
    'Engineering',
    'Medicine',
    'Philosophy'
  ];

  useEffect(() => {
    if (isEditing && id) {
      const book = getBookById(id);
      if (book) {
        setFormData({
          title: book.title,
          author: book.author,
          subject: book.subject,
          description: book.description || '',
          coverImage: book.coverImage || '',
          pdfUrl: book.pdfUrl || '',
          edition: book.edition || '',
          publicationDate: book.publicationDate || '',
          publisher: book.publisher || '',
          isbn: book.isbn || '',
          bookHash: book.bookHash || '',
          source: book.source || ''
        });
      } else {
        addToast({
          type: 'error',
          title: 'Book not found',
          message: 'The requested book could not be found.'
        });
        navigate('/admin/books');
      }
    }
  }, [id, isEditing, addToast, navigate]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    
    // Clear error when user starts typing
    if (errors[name as keyof BookFormData]) {
      setErrors(prev => ({ ...prev, [name]: undefined }));
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Partial<BookFormData> = {};

    if (!formData.title.trim()) {
      newErrors.title = 'Title is required';
    }

    if (!formData.author.trim()) {
      newErrors.author = 'Author is required';
    }

    if (!formData.subject.trim()) {
      newErrors.subject = 'Subject is required';
    }

    if (!formData.description.trim()) {
      newErrors.description = 'Description is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      addToast({
        type: 'error',
        title: 'Validation Error',
        message: 'Please fix the errors below and try again.'
      });
      return;
    }

    setIsLoading(true);

    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 2000));

      const bookData: Book = {
        id: isEditing ? id! : Math.random().toString(36).substr(2, 9),
        title: formData.title.trim(),
        author: formData.author.trim(),
        subject: formData.subject.trim(),
        description: formData.description.trim(),
        coverImage: formData.coverImage.trim() || undefined,
        pdfUrl: formData.pdfUrl.trim() || undefined,
        edition: formData.edition.trim() || undefined,
        publicationDate: formData.publicationDate.trim() || undefined,
        publisher: formData.publisher.trim() || undefined,
        isbn: formData.isbn.trim() || undefined,
        bookHash: formData.bookHash.trim() || undefined,
        source: formData.source.trim() || undefined,
        createdAt: isEditing ? getBookById(id!)?.createdAt || new Date().toISOString() : new Date().toISOString(),
        updatedAt: new Date().toISOString()
      };

      addToast({
        type: 'success',
        title: isEditing ? 'Book updated' : 'Book added',
        message: `"${bookData.title}" has been ${isEditing ? 'updated' : 'added'} successfully.`
      });

      navigate('/admin/books');
    } catch (error) {
      addToast({
        type: 'error',
        title: 'Error',
        message: 'An error occurred while saving the book. Please try again.'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancel = () => {
    navigate('/admin/books');
  };

  return (
    <div className="p-6">
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">
            {isEditing ? 'Edit Book' : 'Add New Book'}
          </h1>
          <p className="mt-1 text-sm text-gray-600">
            {isEditing ? 'Update book information' : 'Fill in the details to add a new book to your collection'}
          </p>
        </div>

        {/* Form */}
        <div className="bg-white shadow rounded-lg">
          <form onSubmit={handleSubmit} className="space-y-6 p-6">
            {/* Title */}
            <Input
              label="Book Title *"
              name="title"
              value={formData.title}
              onChange={handleInputChange}
              placeholder="Enter book title"
              error={errors.title}
            />

            {/* Author */}
            <Input
              label="Author *"
              name="author"
              value={formData.author}
              onChange={handleInputChange}
              placeholder="Enter author name"
              error={errors.author}
            />

            {/* Subject */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Subject *
              </label>
              <select
                name="subject"
                value={formData.subject}
                onChange={handleInputChange}
                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
              >
                <option value="">Select a subject</option>
                {subjects.map(subject => (
                  <option key={subject} value={subject}>
                    {subject}
                  </option>
                ))}
                <option value="Other">Other</option>
              </select>
              {errors.subject && (
                <p className="mt-1 text-sm text-red-600">{errors.subject}</p>
              )}
            </div>

            {/* Custom Subject (if Other is selected) */}
            {formData.subject === 'Other' && (
              <Input
                label="Custom Subject *"
                name="subject"
                value={formData.subject}
                onChange={(e) => {
                  const { value } = e.target;
                  setFormData(prev => ({ ...prev, subject: value }));
                }}
                placeholder="Enter custom subject"
                error={errors.subject}
              />
            )}

            {/* Description */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Description *
              </label>
              <textarea
                name="description"
                value={formData.description}
                onChange={handleInputChange}
                rows={4}
                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                placeholder="Enter book description..."
              />
              {errors.description && (
                <p className="mt-1 text-sm text-red-600">{errors.description}</p>
              )}
            </div>

            {/* Cover Image URL */}
            <Input
              label="Cover Image URL (Optional)"
              name="coverImage"
              value={formData.coverImage}
              onChange={handleInputChange}
              placeholder="https://example.com/cover.jpg"
              helperText="Provide a URL to the book's cover image"
            />

            {/* PDF URL */}
            <Input
              label="PDF File URL (Optional)"
              name="pdfUrl"
              value={formData.pdfUrl}
              onChange={handleInputChange}
              placeholder="https://example.com/book.pdf"
              helperText="Provide a URL to the PDF file for download"
            />

            {/* Additional Metadata Section */}
            <div className="border-t border-gray-200 pt-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Additional Metadata</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Edition */}
                <Input
                  label="Edition (Optional)"
                  name="edition"
                  value={formData.edition}
                  onChange={handleInputChange}
                  placeholder="e.g., 11th Edition"
                  helperText="Book edition information"
                />

                {/* Publication Date */}
                <Input
                  label="Publication Date (Optional)"
                  name="publicationDate"
                  type="date"
                  value={formData.publicationDate}
                  onChange={handleInputChange}
                  helperText="Book publication date"
                />

                {/* Publisher */}
                <Input
                  label="Publisher (Optional)"
                  name="publisher"
                  value={formData.publisher}
                  onChange={handleInputChange}
                  placeholder="e.g., Pearson Education"
                  helperText="Book publisher"
                />

                {/* ISBN */}
                <Input
                  label="ISBN (Optional)"
                  name="isbn"
                  value={formData.isbn}
                  onChange={handleInputChange}
                  placeholder="e.g., 9780134450629"
                  helperText="International Standard Book Number"
                />

                {/* Book Hash */}
                <Input
                  label="Book Hash (Optional)"
                  name="bookHash"
                  value={formData.bookHash}
                  onChange={handleInputChange}
                  placeholder="e.g., d36ed937e4458b1cd1989f0bace9b9bf"
                  helperText="Unique hash identifier for the book file"
                />

                {/* Source */}
                <Input
                  label="Source (Optional)"
                  name="source"
                  value={formData.source}
                  onChange={handleInputChange}
                  placeholder="e.g., Anna's Archive"
                  helperText="Source where the book was obtained"
                />
              </div>
            </div>

            {/* Preview */}
            {(formData.coverImage || formData.title) && (
              <div className="border rounded-lg p-4 bg-gray-50">
                <h3 className="text-sm font-medium text-gray-700 mb-3">Preview</h3>
                <div className="flex items-start space-x-4">
                  <div className="flex-shrink-0">
                    {formData.coverImage ? (
                      <img
                        src={formData.coverImage}
                        alt="Cover preview"
                        className="w-16 h-20 object-cover rounded"
                        onError={(e) => {
                          (e.target as HTMLImageElement).style.display = 'none';
                        }}
                      />
                    ) : (
                      <div className="w-16 h-20 bg-gray-200 rounded flex items-center justify-center">
                        <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                      </div>
                    )}
                  </div>
                  <div className="flex-1">
                    <h4 className="font-medium text-gray-900">
                      {formData.title || 'Book Title'}
                    </h4>
                    <p className="text-sm text-gray-600">
                      by {formData.author || 'Author Name'}
                    </p>
                    {formData.subject && (
                      <span className="inline-block mt-1 px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                        {formData.subject}
                      </span>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Actions */}
            <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
              <Button
                type="button"
                variant="outline"
                onClick={handleCancel}
                disabled={isLoading}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                isLoading={isLoading}
              >
                {isEditing ? 'Update Book' : 'Add Book'}
              </Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};