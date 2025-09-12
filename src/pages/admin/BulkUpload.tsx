import React, { useState, useCallback } from 'react';
import { Button, useToast } from '../../components/ui';
import type { Book } from '../../types';

interface UploadFile {
  id: string;
  name: string;
  size: number;
  type: string;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
  bookData?: Partial<Book>;
}

export const BulkUpload: React.FC = () => {
  const [files, setFiles] = useState<UploadFile[]>([]);
  const [isDragOver, setIsDragOver] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const { addToast } = useToast();

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    
    const droppedFiles = Array.from(e.dataTransfer.files);
    handleFileSelection(droppedFiles);
  }, []);

  const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFiles = Array.from(e.target.files || []);
    handleFileSelection(selectedFiles);
    // Clear input value to allow re-selecting same files
    e.target.value = '';
  };

  const handleFileSelection = (selectedFiles: File[]) => {
    const validFiles = selectedFiles.filter(file => {
      const isValidType = file.type === 'application/pdf' || file.type.startsWith('image/');
      const isValidSize = file.size <= 50 * 1024 * 1024; // 50MB limit
      
      if (!isValidType) {
        addToast({
          type: 'error',
          title: 'Invalid file type',
          message: `${file.name}: Only PDF and image files are allowed.`
        });
        return false;
      }
      
      if (!isValidSize) {
        addToast({
          type: 'error',
          title: 'File too large',
          message: `${file.name}: File size must be less than 50MB.`
        });
        return false;
      }
      
      return true;
    });

    const newFiles: UploadFile[] = validFiles.map(file => ({
      id: Math.random().toString(36).substr(2, 9),
      name: file.name,
      size: file.size,
      type: file.type,
      progress: 0,
      status: 'pending',
      bookData: {
        title: file.name.replace(/\.[^/.]+$/, ''), // Remove file extension
        author: '',
        subject: '',
        description: ''
      }
    }));

    setFiles(prev => [...prev, ...newFiles]);
  };

  const updateFileBookData = (fileId: string, field: keyof Book, value: string) => {
    setFiles(prev => prev.map(file => 
      file.id === fileId
        ? { ...file, bookData: { ...file.bookData, [field]: value } }
        : file
    ));
  };

  const removeFile = (fileId: string) => {
    setFiles(prev => prev.filter(file => file.id !== fileId));
  };

  const uploadFile = async (file: UploadFile): Promise<void> => {
    return new Promise((resolve, reject) => {
      // Simulate file upload with progress
      const uploadInterval = setInterval(() => {
        setFiles(prev => prev.map(f => {
          if (f.id === file.id) {
            const newProgress = Math.min(f.progress + Math.random() * 30, 100);
            const newStatus = newProgress >= 100 ? 'completed' : 'uploading';
            return { ...f, progress: newProgress, status: newStatus };
          }
          return f;
        }));
      }, 500);

      setTimeout(() => {
        clearInterval(uploadInterval);
        
        // Simulate random success/failure
        if (Math.random() > 0.1) { // 90% success rate
          setFiles(prev => prev.map(f => 
            f.id === file.id 
              ? { ...f, progress: 100, status: 'completed' as const }
              : f
          ));
          resolve();
        } else {
          setFiles(prev => prev.map(f => 
            f.id === file.id 
              ? { ...f, status: 'error' as const, error: 'Upload failed. Please try again.' }
              : f
          ));
          reject(new Error('Upload failed'));
        }
      }, 3000 + Math.random() * 2000); // 3-5 seconds
    });
  };

  const handleBulkUpload = async () => {
    const pendingFiles = files.filter(file => 
      file.status === 'pending' && 
      file.bookData?.title?.trim() && 
      file.bookData?.author?.trim() &&
      file.bookData?.subject?.trim()
    );

    if (pendingFiles.length === 0) {
      addToast({
        type: 'warning',
        title: 'No files to upload',
        message: 'Please add files and fill in required book information.'
      });
      return;
    }

    setIsUploading(true);

    try {
      // Upload files concurrently but limit concurrency
      const concurrencyLimit = 3;
      for (let i = 0; i < pendingFiles.length; i += concurrencyLimit) {
        const batch = pendingFiles.slice(i, i + concurrencyLimit);
        await Promise.allSettled(batch.map(uploadFile));
      }

      const completedCount = files.filter(f => f.status === 'completed').length;
      const errorCount = files.filter(f => f.status === 'error').length;

      addToast({
        type: completedCount > 0 ? 'success' : 'error',
        title: 'Upload completed',
        message: `${completedCount} files uploaded successfully${errorCount > 0 ? `, ${errorCount} failed` : ''}.`
      });

    } catch (error) {
      addToast({
        type: 'error',
        title: 'Upload error',
        message: 'An error occurred during bulk upload. Please try again.'
      });
    } finally {
      setIsUploading(false);
    }
  };

  const clearCompleted = () => {
    setFiles(prev => prev.filter(file => file.status !== 'completed'));
  };

  const clearAll = () => {
    setFiles([]);
  };

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getStatusColor = (status: UploadFile['status']) => {
    switch (status) {
      case 'pending': return 'text-gray-600';
      case 'uploading': return 'text-blue-600';
      case 'completed': return 'text-green-600';
      case 'error': return 'text-red-600';
      default: return 'text-gray-600';
    }
  };

  const getStatusIcon = (status: UploadFile['status']) => {
    switch (status) {
      case 'pending':
        return (
          <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        );
      case 'uploading':
        return (
          <svg className="w-5 h-5 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        );
      case 'completed':
        return (
          <svg className="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        );
      case 'error':
        return (
          <svg className="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        );
    }
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Bulk Upload</h1>
        <p className="mt-1 text-sm text-gray-600">
          Upload multiple books and PDFs at once. Drag and drop files or click to select.
        </p>
      </div>

      {/* Upload Area */}
      <div
        className={`relative border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
          isDragOver
            ? 'border-[#ca1d26] bg-red-50'
            : 'border-gray-300 hover:border-gray-400'
        }`}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
      >
        <input
          type="file"
          multiple
          accept=".pdf,image/*"
          onChange={handleFileInput}
          className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
        />
        
        <div className="space-y-4">
          <div className="flex justify-center">
            <svg className="w-12 h-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          </div>
          <div>
            <p className="text-lg font-medium text-gray-900">
              Drop files here or click to select
            </p>
            <p className="text-sm text-gray-500 mt-1">
              Supports PDF and image files up to 50MB each
            </p>
          </div>
          <Button variant="outline">
            Choose Files
          </Button>
        </div>
      </div>

      {/* File List */}
      {files.length > 0 && (
        <div className="mt-8">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-medium text-gray-900">
              Files ({files.length})
            </h2>
            <div className="flex space-x-3">
              <Button
                variant="outline"
                size="sm"
                onClick={clearCompleted}
                disabled={!files.some(f => f.status === 'completed')}
              >
                Clear Completed
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={clearAll}
                disabled={files.length === 0}
              >
                Clear All
              </Button>
              <Button
                onClick={handleBulkUpload}
                disabled={isUploading || !files.some(f => f.status === 'pending')}
                isLoading={isUploading}
              >
                Upload All
              </Button>
            </div>
          </div>

          <div className="space-y-4">
            {files.map((file) => (
              <div key={file.id} className="bg-white rounded-lg border border-gray-200 p-4">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center space-x-3">
                    {getStatusIcon(file.status)}
                    <div>
                      <p className="font-medium text-gray-900">{file.name}</p>
                      <p className="text-sm text-gray-500">{formatFileSize(file.size)}</p>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <span className={`text-sm font-medium ${getStatusColor(file.status)}`}>
                      {file.status.charAt(0).toUpperCase() + file.status.slice(1)}
                    </span>
                    {file.status !== 'uploading' && (
                      <button
                        onClick={() => removeFile(file.id)}
                        className="text-gray-400 hover:text-red-600"
                      >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    )}
                  </div>
                </div>

                {/* Progress Bar */}
                {file.status === 'uploading' && (
                  <div className="mb-3">
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                        style={{ width: `${file.progress}%` }}
                      />
                    </div>
                    <p className="text-xs text-gray-500 mt-1">{Math.round(file.progress)}% uploaded</p>
                  </div>
                )}

                {/* Error Message */}
                {file.status === 'error' && file.error && (
                  <div className="mb-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                    {file.error}
                  </div>
                )}

                {/* Book Information Form */}
                {(file.status === 'pending' || file.status === 'error') && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">
                        Title *
                      </label>
                      <input
                        type="text"
                        value={file.bookData?.title || ''}
                        onChange={(e) => updateFileBookData(file.id, 'title', e.target.value)}
                        className="w-full text-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                        placeholder="Enter book title"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">
                        Author *
                      </label>
                      <input
                        type="text"
                        value={file.bookData?.author || ''}
                        onChange={(e) => updateFileBookData(file.id, 'author', e.target.value)}
                        className="w-full text-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                        placeholder="Enter author name"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">
                        Subject *
                      </label>
                      <input
                        type="text"
                        value={file.bookData?.subject || ''}
                        onChange={(e) => updateFileBookData(file.id, 'subject', e.target.value)}
                        className="w-full text-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                        placeholder="Enter subject"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">
                        Description
                      </label>
                      <input
                        type="text"
                        value={file.bookData?.description || ''}
                        onChange={(e) => updateFileBookData(file.id, 'description', e.target.value)}
                        className="w-full text-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                        placeholder="Enter description (optional)"
                      />
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};