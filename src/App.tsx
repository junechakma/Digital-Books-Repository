import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { CartProvider } from './context/CartContext';
import { ToastProvider } from './components/ui';
import { Header } from './components/layout/Header';
import { AdminLayout } from './components/layout/AdminLayout';
import { HomePage } from './pages/public/HomePage';
import { BooksPage } from './pages/public/BooksPage';
import { CartPage } from './pages/public/CartPage';
import { CheckoutPage } from './pages/public/CheckoutPage';
import { AdminLogin } from './pages/admin/AdminLogin';
import { AdminDashboard } from './pages/admin/AdminDashboard';
import { BooksManagement } from './pages/admin/BooksManagement';
import { BookForm } from './pages/admin/BookForm';
import { BulkUpload } from './pages/admin/BulkUpload';
import './index.css';

function App() {
  return (
    <ToastProvider>
      <AuthProvider>
        <CartProvider>
          <Router>
            <div className="min-h-screen bg-white text-[#2b2e33]">
              <Header />
              <main>
                <Routes>
                  <Route path="/" element={<HomePage />} />
                  <Route path="/books" element={<BooksPage />} />
                  <Route path="/cart" element={<CartPage />} />
                  <Route path="/checkout" element={<CheckoutPage />} />
                  <Route path="/admin/login" element={<AdminLogin />} />
                  <Route path="/admin" element={<AdminLayout />}>
                    <Route index element={<AdminDashboard />} />
                    <Route path="books" element={<BooksManagement />} />
                    <Route path="books/new" element={<BookForm />} />
                    <Route path="books/edit/:id" element={<BookForm />} />
                    <Route path="upload" element={<BulkUpload />} />
                  </Route>
                </Routes>
              </main>
            </div>
          </Router>
        </CartProvider>
      </AuthProvider>
    </ToastProvider>
  );
}

export default App;
