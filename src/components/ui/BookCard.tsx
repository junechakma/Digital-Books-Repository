import React from 'react';
import type { Book } from '../../types';
import { Button } from './Button';
import { useCart } from '../../context/CartContext';
import { useToast } from './Toast';
import { truncateText } from '../../utils';

interface BookCardProps {
  book: Book;
  onViewDetails?: (book: Book) => void;
}

export const BookCard: React.FC<BookCardProps> = ({ book, onViewDetails }) => {
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

  return (
    <div className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-1 flex flex-col h-full">
      <div className="aspect-[3/4] bg-gray-200 flex items-center justify-center">
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
      </div>
      
      <div className="p-4 flex-1 flex flex-col">
        <h3 className="font-semibold text-base mb-2 line-clamp-2 flex-shrink-0">
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
        
        <div className="flex flex-col gap-2 mt-auto">
          {onViewDetails && (
            <Button 
              variant="outline" 
              size="sm" 
              className="w-full"
              onClick={() => onViewDetails(book)}
            >
              View Details
            </Button>
          )}
          <Button 
            size="sm" 
            className="w-full"
            onClick={handleAddToCart}
            disabled={isInCart(book.id)}
          >
            {isInCart(book.id) ? 'In Cart' : 'Add to Cart'}
          </Button>
        </div>
      </div>
    </div>
  );
};