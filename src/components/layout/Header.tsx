import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { Button } from '../ui';

export const Header: React.FC = () => {
  const { user, logout } = useAuth();
  const { total } = useCart();

  return (
    <header className="bg-white shadow-sm border-b">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-20 py-2">
          <Link to="/" className="flex items-center space-x-2 sm:space-x-3 min-w-0 flex-shrink-0">
            <img
              src="/logo.png"
              alt="Digital Books Wallet"
              className="h-12 sm:h-16 w-auto flex-shrink-0"
              onError={(e) => {
                // Fallback if image doesn't exist
                e.currentTarget.style.display = 'none';
              }}
            />
            <img
              src="/ugc-latest.png"
              alt="UGC Logo"
              className="hidden sm:block h-10 sm:h-12 w-auto flex-shrink-0"
              onError={(e) => {
                // Hide if image doesn't exist
                e.currentTarget.style.display = 'none';
              }}
            />
          </Link>
          
          <nav className="hidden md:flex space-x-8">
            <Link to="/" className="text-gray-700 hover:text-[#ca1d26] transition-colors">
              Home
            </Link>
            <Link to="/books" className="text-gray-700 hover:text-[#ca1d26] transition-colors">
              Books
            </Link>
            {user?.isAdmin && (
              <Link to="/admin" className="text-gray-700 hover:text-[#ca1d26] transition-colors">
                Admin
              </Link>
            )}
          </nav>

          <div className="flex items-center space-x-4">
            <Link
              to="/cart"
              className="relative p-2 text-gray-700 hover:text-[#ca1d26] transition-colors"
            >
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2 8h12M10 21a1 1 0 100-2 1 1 0 000 2zm5 0a1 1 0 100-2 1 1 0 000 2z" />
              </svg>
              {total > 0 && (
                <span className="absolute -top-1 -right-1 bg-[#ca1d26] text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                  {total}
                </span>
              )}
            </Link>
            
            {user ? (
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-700 hidden sm:inline truncate">
                  {user.email}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={logout}
                  className="flex-shrink-0"
                >
                  Logout
                </Button>
              </div>
            ) : (
              <Link to="/admin/login">
                <Button variant="outline" size="sm">
                  Admin Login
                </Button>
              </Link>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};