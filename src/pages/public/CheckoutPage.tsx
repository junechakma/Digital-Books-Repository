import React, { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Button, Input, Modal, useToast } from '../../components/ui';
import { useCart } from '../../context/CartContext';
import { validateUniversityEmail, generateOTP } from '../../utils';

export const CheckoutPage: React.FC = () => {
  const { items, total, clearCart } = useCart();
  const { addToast } = useToast();
  const navigate = useNavigate();

  const [email, setEmail] = useState('');
  const [isEmailValid, setIsEmailValid] = useState(false);
  const [showOTPModal, setShowOTPModal] = useState(false);
  const [otp, setOtp] = useState(['', '', '', '', '', '']);
  const [generatedOTP, setGeneratedOTP] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [step, setStep] = useState<'email' | 'otp' | 'download'>('email');

  // Redirect if cart is empty
  if (items.length === 0) {
    return <Navigate to="/cart" replace />;
  }

  const handleEmailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const emailValue = e.target.value;
    setEmail(emailValue);
    setIsEmailValid(validateUniversityEmail(emailValue));
  };

  const handleEmailSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!isEmailValid) {
      addToast({
        type: 'error',
        title: 'Invalid email',
        message: 'Please enter a valid university email address.'
      });
      return;
    }

    setIsLoading(true);
    
    // Simulate sending OTP
    await new Promise(resolve => setTimeout(resolve, 1500));
    
    const newOTP = generateOTP();
    setGeneratedOTP(newOTP);
    setShowOTPModal(true);
    setStep('otp');
    setIsLoading(false);

    addToast({
      type: 'success',
      title: 'OTP sent',
      message: `Verification code sent to ${email}. Demo OTP: ${newOTP}`
    });
  };

  const handleOTPChange = (index: number, value: string) => {
    if (value.length > 1) return;
    
    const newOtp = [...otp];
    newOtp[index] = value;
    setOtp(newOtp);

    // Auto-focus next input
    if (value && index < 5) {
      const nextInput = document.getElementById(`otp-${index + 1}`);
      nextInput?.focus();
    }
  };

  const handleOTPKeyDown = (index: number, e: React.KeyboardEvent) => {
    if (e.key === 'Backspace' && !otp[index] && index > 0) {
      const prevInput = document.getElementById(`otp-${index - 1}`);
      prevInput?.focus();
    }
  };

  const handleOTPSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const otpString = otp.join('');
    if (otpString.length !== 6) {
      addToast({
        type: 'error',
        title: 'Incomplete OTP',
        message: 'Please enter the complete 6-digit verification code.'
      });
      return;
    }

    setIsLoading(true);
    
    // Simulate OTP verification
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    if (otpString === generatedOTP) {
      setStep('download');
      addToast({
        type: 'success',
        title: 'Email verified',
        message: 'Your email has been verified successfully!'
      });
    } else {
      addToast({
        type: 'error',
        title: 'Invalid OTP',
        message: 'The verification code is incorrect. Please try again.'
      });
    }
    
    setIsLoading(false);
  };

  const handleDownload = async (bookId: string, title: string) => {
    setIsLoading(true);
    
    // Simulate download preparation
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // In a real app, this would generate a secure download link
    const downloadUrl = `/books/${bookId}.pdf`;
    
    // Create download link
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = `${title}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    addToast({
      type: 'success',
      title: 'Download started',
      message: `"${title}" is being downloaded.`
    });
    
    setIsLoading(false);
  };

  const handleFinish = () => {
    clearCart();
    addToast({
      type: 'success',
      title: 'Thank you!',
      message: 'Enjoy reading your downloaded books!'
    });
    navigate('/');
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Checkout</h1>
          <p className="text-gray-600">
            Verify your university email to download your selected books
          </p>
        </div>

        <div className="grid lg:grid-cols-2 gap-8">
          {/* Left Column - Process */}
          <div className="space-y-6">
            {step === 'email' && (
              <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-xl font-semibold mb-4">
                  Step 1: Email Verification
                </h2>
                <form onSubmit={handleEmailSubmit} className="space-y-4">
                  <Input
                    label="University Email Address"
                    type="email"
                    value={email}
                    onChange={handleEmailChange}
                    placeholder="your.name@university.edu"
                    error={email && !isEmailValid ? 'Please enter a valid university email address' : ''}
                    helperText="We only accept emails from accredited educational institutions"
                  />
                  <Button
                    type="submit"
                    className="w-full"
                    disabled={!isEmailValid}
                    isLoading={isLoading}
                  >
                    Send Verification Code
                  </Button>
                </form>
              </div>
            )}

            {step === 'download' && (
              <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-xl font-semibold mb-4 text-green-600">
                  ✓ Email Verified Successfully!
                </h2>
                <p className="text-gray-600 mb-6">
                  Your email has been verified. You can now download your selected books.
                </p>
                
                <div className="space-y-4">
                  {items.map((item) => (
                    <div key={item.bookId} className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex-1">
                        <h3 className="font-medium">{item.book.title}</h3>
                        <p className="text-sm text-gray-600">by {item.book.author}</p>
                      </div>
                      <Button
                        onClick={() => handleDownload(item.bookId, item.book.title)}
                        isLoading={isLoading}
                        className="ml-4"
                      >
                        Download PDF
                      </Button>
                    </div>
                  ))}
                </div>

                <div className="mt-6 pt-6 border-t">
                  <Button
                    onClick={handleFinish}
                    variant="outline"
                    className="w-full"
                  >
                    Finish & Return to Home
                  </Button>
                </div>
              </div>
            )}
          </div>

          {/* Right Column - Order Summary */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow p-6 sticky top-8">
              <h2 className="text-xl font-semibold mb-4">Order Summary</h2>
              
              <div className="space-y-3 mb-6">
                {items.map((item) => (
                  <div key={item.bookId} className="flex justify-between items-start">
                    <div className="flex-1 min-w-0 pr-4">
                      <p className="font-medium text-sm truncate">{item.book.title}</p>
                      <p className="text-xs text-gray-500">{item.book.author}</p>
                    </div>
                    <span className="text-green-600 font-medium">Free</span>
                  </div>
                ))}
              </div>

              <div className="border-t pt-4 mb-4">
                <div className="flex justify-between items-center">
                  <span className="font-medium">Total Items:</span>
                  <span className="font-medium">{total}</span>
                </div>
                <div className="flex justify-between items-center mt-2">
                  <span className="font-medium">Total Cost:</span>
                  <span className="font-medium text-green-600">Free</span>
                </div>
              </div>

              <div className="bg-green-50 border border-green-200 rounded-md p-3">
                <p className="text-sm text-green-800 font-medium">
                  🎓 Educational Access
                </p>
                <p className="text-xs text-green-700 mt-1">
                  All academic resources are provided free of charge to verified university students and faculty.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* OTP Modal */}
      <Modal
        isOpen={showOTPModal}
        onClose={() => {}}
        title="Enter Verification Code"
        size="sm"
      >
        <form onSubmit={handleOTPSubmit} className="space-y-4">
          <p className="text-gray-600 mb-4">
            We've sent a 6-digit verification code to {email}
          </p>
          
          <div className="flex justify-center space-x-2">
            {otp.map((digit, index) => (
              <input
                key={index}
                id={`otp-${index}`}
                type="text"
                value={digit}
                onChange={(e) => handleOTPChange(index, e.target.value)}
                onKeyDown={(e) => handleOTPKeyDown(index, e)}
                className="w-12 h-12 text-center text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                maxLength={1}
                pattern="[0-9]"
              />
            ))}
          </div>

          <Button
            type="submit"
            className="w-full mt-6"
            isLoading={isLoading}
          >
            Verify Code
          </Button>

          <p className="text-xs text-gray-500 text-center">
            Demo OTP: {generatedOTP}
          </p>
        </form>
      </Modal>
    </div>
  );
};