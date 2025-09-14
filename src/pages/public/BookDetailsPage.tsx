import React, { useState } from 'react';
import { useParams, Link, Navigate } from 'react-router-dom';
import { Button, PDFViewer, useToast } from '../../components/ui';
import { useCart } from '../../context/CartContext';
import { getBookById } from '../../data/mockBooks';
import { formatDate, truncateText } from '../../utils';

export const BookDetailsPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { addToCart, isInCart } = useCart();
  const { addToast } = useToast();
  const [showPDFViewer, setShowPDFViewer] = useState(false);

  if (!id) {
    return <Navigate to="/books" replace />;
  }

  const book = getBookById(id);

  if (!book) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Book Not Found</h2>
          <p className="text-gray-600 mb-6">The book you're looking for doesn't exist.</p>
          <Link to="/books">
            <Button>Browse Books</Button>
          </Link>
        </div>
      </div>
    );
  }

  const handleAddToCart = () => {
    if (isInCart(book.id)) {
      addToast({
        type: 'info',
        title: 'Already in cart',
        message: 'This book is already in your cart.'
      });
      return;
    }

    addToCart(book);
    addToast({
      type: 'success',
      title: 'Added to cart',
      message: `"${truncateText(book.title, 30)}" has been added to your cart.`
    });
  };

  const handleViewPDF = () => {
    setShowPDFViewer(true);
  };

  const handleClosePDF = () => {
    setShowPDFViewer(false);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Breadcrumb */}
        <nav className="mb-8" aria-label="Breadcrumb">
          <ol className="flex items-center space-x-2 text-sm text-gray-500">
            <li>
              <Link to="/" className="hover:text-gray-700">
                Home
              </Link>
            </li>
            <li>
              <span className="mx-2">/</span>
              <Link to="/books" className="hover:text-gray-700">
                Books
              </Link>
            </li>
            <li>
              <span className="mx-2">/</span>
              <span className="text-gray-900">{truncateText(book.title, 30)}</span>
            </li>
          </ol>
        </nav>

        {!showPDFViewer ? (
          /* Book Details View */
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Book Cover */}
            <div className="lg:col-span-1">
              <div className="aspect-[3/4] bg-white rounded-lg shadow-md flex items-center justify-center mb-6">
                {book.coverImage ? (
                  <img
                    src={book.coverImage}
                    alt={book.title}
                    className="w-full h-full object-cover rounded-lg"
                  />
                ) : (
                  <div className="text-center text-gray-500">
                    <svg className="w-20 h-20 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <p className="text-lg">No Cover Available</p>
                  </div>
                )}
              </div>

              {/* Action Buttons */}
              <div className="space-y-3">
                <Button
                  onClick={handleViewPDF}
                  className="w-full"
                  size="lg"
                >
                  📖 View PDF
                </Button>

                <Button
                  onClick={handleAddToCart}
                  variant={isInCart(book.id) ? "outline" : "primary"}
                  className="w-full"
                  size="lg"
                  disabled={isInCart(book.id)}
                >
                  {isInCart(book.id) ? '✓ In Cart' : '🛒 Add to Cart'}
                </Button>

                <Link to="/cart" className="block">
                  <Button variant="outline" className="w-full" size="lg">
                    🛍️ View Cart
                  </Button>
                </Link>
              </div>
            </div>

            {/* Book Information */}
            <div className="lg:col-span-2">
              <div className="bg-white rounded-lg shadow-md p-6">
                <div className="mb-6">
                  <h1 className="text-3xl font-bold text-gray-900 mb-3">
                    {book.title}
                  </h1>
                  <p className="text-xl text-gray-600 mb-2">
                    by {book.author}
                  </p>
                  <div className="flex items-center space-x-4 text-sm text-gray-500">
                    <span className="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                      {book.subject}
                    </span>
                    <span>Added {formatDate(book.createdAt)}</span>
                  </div>
                </div>

                {book.description && (
                  <div className="mb-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-3">
                      Description
                    </h2>
                    <p className="text-gray-700 leading-relaxed">
                      {book.description}
                    </p>
                  </div>
                )}

                {/* Additional Information */}
                <div className="border-t pt-6">
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">
                    Book Information
                  </h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <h3 className="text-sm font-medium text-gray-500 mb-1">Subject</h3>
                      <p className="text-gray-900">{book.subject}</p>
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-gray-500 mb-1">Author</h3>
                      <p className="text-gray-900">{book.author}</p>
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-gray-500 mb-1">Added</h3>
                      <p className="text-gray-900">{formatDate(book.createdAt)}</p>
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-gray-500 mb-1">Format</h3>
                      <p className="text-gray-900">PDF Document</p>
                    </div>
                  </div>
                </div>

                {/* Access Notice */}
                <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                  <div className="flex items-start">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <h3 className="text-sm font-medium text-green-800">
                        Free Academic Access
                      </h3>
                      <p className="mt-1 text-sm text-green-700">
                        This book is available free of charge for university students and faculty.
                        Add it to your cart and verify your university email to download.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        ) : (
          /* PDF Viewer */
          <div className="space-y-6">
            {/* PDF Viewer Header */}
            <div className="flex items-center justify-between">
              <h1 className="text-2xl font-bold text-gray-900">
                Viewing: {truncateText(book.title, 50)}
              </h1>
              <Button
                variant="outline"
                onClick={handleClosePDF}
                className="flex items-center space-x-2"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Close Viewer</span>
              </Button>
            </div>

            {/* PDF Viewer Component */}
            {book.pdfUrl && (
              <PDFViewer
                url={book.pdfUrl}
                title={book.title}
                className="min-h-screen"
              />
            )}
          </div>
        )}
      </div>
    </div>
  );
};