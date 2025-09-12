import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, useToast } from '../../components/ui';
import { useCart } from '../../context/CartContext';
import { formatDate } from '../../utils';

export const CartPage: React.FC = () => {
  const { items, removeFromCart, clearCart, total } = useCart();
  const { addToast } = useToast();
  const navigate = useNavigate();

  const handleRemoveItem = (bookId: string, title: string) => {
    removeFromCart(bookId);
    addToast({
      type: 'success',
      title: 'Item removed',
      message: `"${title}" has been removed from your cart.`
    });
  };

  const handleClearCart = () => {
    if (items.length === 0) return;
    
    clearCart();
    addToast({
      type: 'success',
      title: 'Cart cleared',
      message: 'All items have been removed from your cart.'
    });
  };

  const handleCheckout = () => {
    if (items.length === 0) {
      addToast({
        type: 'warning',
        title: 'Empty cart',
        message: 'Please add some books to your cart before checking out.'
      });
      return;
    }
    navigate('/checkout');
  };

  if (items.length === 0) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-8">Your Cart</h1>
          
          <div className="text-center py-16">
            <svg className="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2 8h12M10 21a1 1 0 100-2 1 1 0 000 2zm5 0a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
            <h3 className="text-lg font-medium text-gray-900 mb-2">Your cart is empty</h3>
            <p className="text-gray-500 mb-6">
              Start browsing our collection to add books to your cart.
            </p>
            <Link to="/books">
              <Button>Browse Books</Button>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            Your Cart ({total} {total === 1 ? 'item' : 'items'})
          </h1>
          <Button
            variant="outline"
            onClick={handleClearCart}
            className="text-red-600 border-red-600 hover:bg-red-50"
          >
            Clear Cart
          </Button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
          {/* Cart Items */}
          <div className="lg:col-span-2 space-y-4">
            {items.map((item) => (
              <div
                key={item.bookId}
                className="bg-white rounded-lg shadow-sm p-4 sm:p-6 flex flex-col sm:flex-row gap-4"
              >
                <div className="w-16 h-20 bg-gray-200 rounded flex-shrink-0 flex items-center justify-center mx-auto sm:mx-0">
                  {item.book.coverImage ? (
                    <img
                      src={item.book.coverImage}
                      alt={item.book.title}
                      className="w-full h-full object-cover rounded"
                    />
                  ) : (
                    <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <h3 className="font-semibold text-lg text-gray-900 mb-1">
                    {item.book.title}
                  </h3>
                  <p className="text-gray-600 text-sm mb-1">
                    by {item.book.author}
                  </p>
                  <p className="text-gray-500 text-sm mb-2">
                    {item.book.subject}
                  </p>
                  <p className="text-gray-400 text-xs">
                    Added {formatDate(item.addedAt)}
                  </p>
                </div>

                <div className="flex justify-center sm:justify-end sm:flex-col sm:justify-between">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleRemoveItem(item.bookId, item.book.title)}
                    className="text-red-600 border-red-600 hover:bg-red-50"
                  >
                    Remove
                  </Button>
                </div>
              </div>
            ))}
          </div>

          {/* Cart Summary */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-sm p-6 sticky top-8">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                Order Summary
              </h2>
              
              <div className="space-y-2 mb-6">
                <div className="flex justify-between">
                  <span className="text-gray-600">Items</span>
                  <span className="font-medium">{total}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Total Cost</span>
                  <span className="font-medium text-green-600">Free</span>
                </div>
              </div>

              <div className="border-t pt-4 mb-6">
                <div className="flex justify-between text-lg font-semibold">
                  <span>Total</span>
                  <span className="text-green-600">Free</span>
                </div>
                <p className="text-sm text-gray-500 mt-1">
                  All academic resources are provided free of charge
                </p>
              </div>

              <Button
                onClick={handleCheckout}
                className="w-full mb-4"
                size="lg"
              >
                Proceed to Checkout
              </Button>

              <Link to="/books" className="block">
                <Button variant="outline" className="w-full">
                  Continue Shopping
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};