import React from 'react';
import { Link } from 'react-router-dom';
import type { Book } from '../../types';
import { Button } from './Button';
import { useCart } from '../../context/CartContext';
import { useToast } from './Toast';
import { truncateText } from '../../utils';

interface BookCardProps {
  book: Book;
}

export const BookCard: React.FC<BookCardProps> = ({ book }) => {
  const { addToCart, isInCart } = useCart();
  const { addToast } = useToast();

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

  const handleCardClick = (e: React.MouseEvent) => {
    // Prevent navigation if clicking on buttons
    if ((e.target as HTMLElement).tagName === 'BUTTON' || (e.target as HTMLElement).closest('button')) {
      e.stopPropagation();
    }
  };

  return (
    <Link to={`/books/${book.id}`} className="block">
      <div
        onClick={handleCardClick}
        className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-1 flex flex-col h-full cursor-pointer relative group"
      >
        {/* Cover Image with Overlay */}
        <div className="aspect-[3/4] bg-gray-200 flex items-center justify-center relative">
          {book.coverImage ? (
            <img
              src={book.coverImage}
              alt={book.title}
              className="w-full h-full object-cover"
            />
          ) : (
            <div className="text-center text-gray-500">
              <svg className="w-16 h-16 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              <p className="text-sm">No Cover</p>
            </div>
          )}

          {/* View PDF Overlay */}
          <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all duration-300 flex items-center justify-center">
            <div className="opacity-0 group-hover:opacity-100 transition-opacity duration-300 text-center text-white">
              <svg className="w-12 h-12 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <p className="text-sm font-medium">View PDF</p>
            </div>
          </div>
        </div>

        <div className="p-4 flex-1 flex flex-col">
          <h3 className="font-semibold text-base mb-2 line-clamp-2 flex-shrink-0 group-hover:text-[#ca1d26] transition-colors">
            {book.title}
          </h3>
          <p className="text-gray-600 text-sm mb-1 flex-shrink-0">
            by {book.author}
          </p>
          <p className="text-gray-500 text-sm mb-3 flex-shrink-0">
            {book.subject}
          </p>

          {book.description && (
            <p className="text-gray-600 text-sm mb-4 line-clamp-3">
              {book.description}
            </p>
          )}

          {/* Add to Cart Button */}
          <div className="mt-auto">
            <Button
              size="sm"
              className={`w-full flex items-center justify-center space-x-2 ${
                isInCart(book.id) ? 'bg-green-600 hover:bg-green-700' : ''
              }`}
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                handleAddToCart();
              }}
              disabled={isInCart(book.id)}
            >
              {isInCart(book.id) ? (
                <>
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  <span>In Cart</span>
                </>
              ) : (
                <>
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2 8h12M10 21a1 1 0 100-2 1 1 0 000 2zm5 0a1 1 0 100-2 1 1 0 000 2z" />
                  </svg>
                  <span>Add to Cart</span>
                </>
              )}
            </Button>
          </div>
        </div>
      </div>
    </Link>
  );
};