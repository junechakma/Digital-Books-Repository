import React, { createContext, useContext, useReducer, useEffect } from 'react';
import type { Book, CartItem, CartState } from '../types';
import { useLocalStorage } from '../hooks/useLocalStorage';

interface CartContextType extends CartState {
  addToCart: (book: Book) => void;
  removeFromCart: (bookId: string) => void;
  clearCart: () => void;
  isInCart: (bookId: string) => boolean;
}

const CartContext = createContext<CartContextType | undefined>(undefined);

type CartAction = 
  | { type: 'ADD_TO_CART'; payload: Book }
  | { type: 'REMOVE_FROM_CART'; payload: string }
  | { type: 'CLEAR_CART' }
  | { type: 'LOAD_CART'; payload: CartItem[] };

const cartReducer = (state: CartState, action: CartAction): CartState => {
  switch (action.type) {
    case 'ADD_TO_CART': {
      const existingItem = state.items.find(item => item.bookId === action.payload.id);
      if (existingItem) {
        return state; // Item already in cart
      }
      
      const newItem: CartItem = {
        bookId: action.payload.id,
        book: action.payload,
        addedAt: new Date().toISOString()
      };
      
      return {
        ...state,
        items: [...state.items, newItem],
        total: state.total + 1
      };
    }
    
    case 'REMOVE_FROM_CART': {
      const filteredItems = state.items.filter(item => item.bookId !== action.payload);
      return {
        ...state,
        items: filteredItems,
        total: filteredItems.length
      };
    }
    
    case 'CLEAR_CART':
      return {
        items: [],
        total: 0
      };
    
    case 'LOAD_CART':
      return {
        items: action.payload,
        total: action.payload.length
      };
    
    default:
      return state;
  }
};

export const useCart = () => {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
};

export const CartProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [cartState, dispatch] = useReducer(cartReducer, {
    items: [],
    total: 0
  });
  
  const [storedCart, setStoredCart] = useLocalStorage<CartItem[]>('cart', []);

  useEffect(() => {
    if (storedCart.length > 0) {
      dispatch({ type: 'LOAD_CART', payload: storedCart });
    }
  }, []);

  useEffect(() => {
    setStoredCart(cartState.items);
  }, [cartState.items, setStoredCart]);

  const addToCart = (book: Book) => {
    dispatch({ type: 'ADD_TO_CART', payload: book });
  };

  const removeFromCart = (bookId: string) => {
    dispatch({ type: 'REMOVE_FROM_CART', payload: bookId });
  };

  const clearCart = () => {
    dispatch({ type: 'CLEAR_CART' });
  };

  const isInCart = (bookId: string): boolean => {
    return cartState.items.some(item => item.bookId === bookId);
  };

  return (
    <CartContext.Provider value={{
      ...cartState,
      addToCart,
      removeFromCart,
      clearCart,
      isInCart
    }}>
      {children}
    </CartContext.Provider>
  );
};