import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Button, Input, useToast } from '../../components/ui';
import { getBookById } from '../../data/mockBooks';
import type { Book } from '../../types';

interface BookFormData {
  title: string;
  author: string;
  subject: string;
  semester: string;
  academicYear: string;
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

interface FileUploadState {
  pdf: {
    file: File | null;
    uploading: boolean;
    progress: number;
    uploaded: boolean;
    url: string;
  };
  cover: {
    file: File | null;
    uploading: boolean;
    progress: number;
    uploaded: boolean;
    url: string;
    preview: string;
  };
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
    semester: '',
    academicYear: new Date().getFullYear().toString(),
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

  // File upload state
  const [fileUploads, setFileUploads] = useState<FileUploadState>({
    pdf: {
      file: null,
      uploading: false,
      progress: 0,
      uploaded: false,
      url: ''
    },
    cover: {
      file: null,
      uploading: false,
      progress: 0,
      uploaded: false,
      url: '',
      preview: ''
    }
  });

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
    'Philosophy',
    'Business',
    'Accounting',
    'Marketing'
  ];

  // Semester options
  const semesters = [
    'January Semester',
    'May Semester',
    'September Semester'
  ];

  // Academic year options (current year and next 2 years)
  const currentYear = new Date().getFullYear();
  const academicYears = [
    currentYear.toString(),
    (currentYear + 1).toString(),
    (currentYear + 2).toString()
  ];

  useEffect(() => {
    if (isEditing && id) {
      const book = getBookById(id);
      if (book) {
        setFormData({
          title: book.title,
          author: book.author,
          subject: book.subject,
          semester: (book as any).semester || '',
          academicYear: (book as any).academicYear || new Date().getFullYear().toString(),
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

  // File upload handlers
  const handleFileSelect = (type: 'pdf' | 'cover', file: File) => {
    if (type === 'pdf') {
      // Validate PDF file
      if (file.type !== 'application/pdf') {
        addToast({
          type: 'error',
          title: 'Invalid File Type',
          message: 'Please select a PDF file.'
        });
        return;
      }
      if (file.size > 50 * 1024 * 1024) { // 50MB limit
        addToast({
          type: 'error',
          title: 'File Too Large',
          message: 'PDF file must be smaller than 50MB.'
        });
        return;
      }
    } else {
      // Validate image file
      if (!file.type.startsWith('image/')) {
        addToast({
          type: 'error',
          title: 'Invalid File Type',
          message: 'Please select an image file (JPG, PNG, GIF).'
        });
        return;
      }
      if (file.size > 5 * 1024 * 1024) { // 5MB limit for images
        addToast({
          type: 'error',
          title: 'File Too Large',
          message: 'Image file must be smaller than 5MB.'
        });
        return;
      }
    }

    setFileUploads(prev => ({
      ...prev,
      [type]: {
        ...prev[type],
        file,
        preview: type === 'cover' ? URL.createObjectURL(file) : ''
      }
    }));
  };

  const uploadFile = async (type: 'pdf' | 'cover') => {
    const fileState = fileUploads[type];
    if (!fileState.file) return;

    // Check if required fields are filled for path generation
    if (!formData.title.trim() || !formData.subject.trim() || !formData.semester.trim() || !formData.academicYear.trim()) {
      addToast({
        type: 'error',
        title: 'Missing Information',
        message: 'Please fill in Title, Subject, Semester, and Academic Year before uploading files.'
      });
      return;
    }

    setFileUploads(prev => ({
      ...prev,
      [type]: { ...prev[type], uploading: true, progress: 0 }
    }));

    try {
      const formDataToSend = new FormData();
      formDataToSend.append(type === 'pdf' ? 'pdf_file' : 'cover_image', fileState.file);
      formDataToSend.append('upload_type', `${type}_only`);
      formDataToSend.append('title', formData.title.trim());
      formDataToSend.append('subject', formData.subject.trim());
      formDataToSend.append('semester', formData.semester.trim());
      formDataToSend.append('academic_year', formData.academicYear.trim());

      // Simulate upload progress (replace with real API call)
      const simulateProgress = () => {
        return new Promise((resolve) => {
          let progress = 0;
          const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress >= 100) {
              progress = 100;
              clearInterval(interval);
              resolve(null);
            }
            setFileUploads(prev => ({
              ...prev,
              [type]: { ...prev[type], progress }
            }));
          }, 200);
        });
      };

      await simulateProgress();

      // Generate the file path based on our routing system
      const safeTitleSlug = formData.title.trim().replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, '_');
      const safeSubjectSlug = formData.subject.replace(/\s+/g, '_');
      const safeSemesterSlug = formData.semester.replace(/\s+/g, '_');
      const fileExtension = type === 'pdf' ? 'pdf' : fileState.file.name.split('.').pop();
      const fileName = type === 'pdf' ? 'book.pdf' : `cover.${fileExtension}`;

      const filePath = `uploads/books/${formData.academicYear}/${safeSemesterSlug}/${safeSubjectSlug}/${safeTitleSlug}/${fileName}`;

      setFileUploads(prev => ({
        ...prev,
        [type]: {
          ...prev[type],
          uploading: false,
          uploaded: true,
          url: filePath,
          progress: 100
        }
      }));

      // Update form data with the file URL
      setFormData(prev => ({
        ...prev,
        [type === 'pdf' ? 'pdfUrl' : 'coverImage']: filePath
      }));

      addToast({
        type: 'success',
        title: 'Upload Successful',
        message: `${type === 'pdf' ? 'PDF' : 'Cover image'} uploaded successfully!`
      });

    } catch (error) {
      setFileUploads(prev => ({
        ...prev,
        [type]: { ...prev[type], uploading: false, progress: 0 }
      }));

      addToast({
        type: 'error',
        title: 'Upload Failed',
        message: `Failed to upload ${type === 'pdf' ? 'PDF' : 'cover image'}. Please try again.`
      });
    }
  };

  const removeFile = (type: 'pdf' | 'cover') => {
    setFileUploads(prev => ({
      ...prev,
      [type]: {
        file: null,
        uploading: false,
        progress: 0,
        uploaded: false,
        url: '',
        preview: ''
      }
    }));

    setFormData(prev => ({
      ...prev,
      [type === 'pdf' ? 'pdfUrl' : 'coverImage']: ''
    }));
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

    if (!formData.semester.trim()) {
      newErrors.semester = 'Semester is required';
    }

    if (!formData.academicYear.trim()) {
      newErrors.academicYear = 'Academic year is required';
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

            {/* Academic Year and Semester Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Academic Year */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Academic Year *
                </label>
                <select
                  name="academicYear"
                  value={formData.academicYear}
                  onChange={handleInputChange}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                >
                  {academicYears.map(year => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
                {errors.academicYear && (
                  <p className="mt-1 text-sm text-red-600">{errors.academicYear}</p>
                )}
              </div>

              {/* Semester */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Semester *
                </label>
                <select
                  name="semester"
                  value={formData.semester}
                  onChange={handleInputChange}
                  className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
                >
                  <option value="">Select a semester</option>
                  {semesters.map(semester => (
                    <option key={semester} value={semester}>
                      {semester}
                    </option>
                  ))}
                </select>
                {errors.semester && (
                  <p className="mt-1 text-sm text-red-600">{errors.semester}</p>
                )}
              </div>
            </div>

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

            {/* File Upload Section */}
            <div className="border-t border-gray-200 pt-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">File Uploads</h3>
              <p className="text-sm text-gray-600 mb-4">
                Upload files to be automatically organized in: {formData.academicYear}/{formData.semester?.replace(/\s+/g, '_')}/{formData.subject?.replace(/\s+/g, '_')}/{formData.title?.replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, '_')}/
              </p>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* PDF Upload */}
                <div className="space-y-3">
                  <label className="block text-sm font-medium text-gray-700">
                    PDF File
                  </label>

                  {!fileUploads.pdf.file && !fileUploads.pdf.uploaded ? (
                    <div
                      className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-[#ca1d26] transition-colors cursor-pointer"
                      onClick={() => document.getElementById('pdf-upload')?.click()}
                      onDragOver={(e) => {
                        e.preventDefault();
                        e.currentTarget.classList.add('border-[#ca1d26]', 'bg-red-50');
                      }}
                      onDragLeave={(e) => {
                        e.preventDefault();
                        e.currentTarget.classList.remove('border-[#ca1d26]', 'bg-red-50');
                      }}
                      onDrop={(e) => {
                        e.preventDefault();
                        e.currentTarget.classList.remove('border-[#ca1d26]', 'bg-red-50');
                        const files = Array.from(e.dataTransfer.files);
                        if (files.length > 0) {
                          handleFileSelect('pdf', files[0]);
                        }
                      }}
                    >
                      <svg className="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                      </svg>
                      <div className="mt-4">
                        <p className="text-sm text-gray-600">
                          <span className="font-medium text-[#ca1d26]">Click to upload</span> or drag and drop
                        </p>
                        <p className="text-xs text-gray-500 mt-1">
                          PDF files up to 50MB
                        </p>
                      </div>
                    </div>
                  ) : fileUploads.pdf.uploading ? (
                    <div className="border border-gray-300 rounded-lg p-4">
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium text-gray-700">
                          {fileUploads.pdf.file?.name}
                        </span>
                        <span className="text-sm text-gray-500">
                          {Math.round(fileUploads.pdf.progress)}%
                        </span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2">
                        <div
                          className="bg-[#ca1d26] h-2 rounded-full transition-all duration-300"
                          style={{ width: `${fileUploads.pdf.progress}%` }}
                        ></div>
                      </div>
                      <p className="text-xs text-gray-500 mt-1">Uploading...</p>
                    </div>
                  ) : fileUploads.pdf.uploaded ? (
                    <div className="border border-green-300 bg-green-50 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                          <svg className="h-8 w-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
                          </svg>
                          <div>
                            <p className="text-sm font-medium text-gray-700">
                              {fileUploads.pdf.file?.name}
                            </p>
                            <p className="text-xs text-green-600">Upload complete</p>
                          </div>
                        </div>
                        <button
                          type="button"
                          onClick={() => removeFile('pdf')}
                          className="text-red-600 hover:text-red-800"
                        >
                          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="border border-gray-300 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                          <svg className="h-8 w-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
                          </svg>
                          <div>
                            <p className="text-sm font-medium text-gray-700">
                              {fileUploads.pdf.file?.name}
                            </p>
                            <p className="text-xs text-gray-500">Ready to upload</p>
                          </div>
                        </div>
                        <div className="flex space-x-2">
                          <button
                            type="button"
                            onClick={() => uploadFile('pdf')}
                            className="px-3 py-1 text-sm bg-[#ca1d26] text-white rounded hover:bg-red-700"
                          >
                            Upload
                          </button>
                          <button
                            type="button"
                            onClick={() => removeFile('pdf')}
                            className="text-red-600 hover:text-red-800"
                          >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </div>
                      </div>
                    </div>
                  )}

                  <input
                    id="pdf-upload"
                    type="file"
                    accept=".pdf"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) {
                        handleFileSelect('pdf', file);
                      }
                    }}
                  />
                </div>

                {/* Cover Image Upload */}
                <div className="space-y-3">
                  <label className="block text-sm font-medium text-gray-700">
                    Cover Image
                  </label>

                  {!fileUploads.cover.file && !fileUploads.cover.uploaded ? (
                    <div
                      className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-[#ca1d26] transition-colors cursor-pointer"
                      onClick={() => document.getElementById('cover-upload')?.click()}
                      onDragOver={(e) => {
                        e.preventDefault();
                        e.currentTarget.classList.add('border-[#ca1d26]', 'bg-red-50');
                      }}
                      onDragLeave={(e) => {
                        e.preventDefault();
                        e.currentTarget.classList.remove('border-[#ca1d26]', 'bg-red-50');
                      }}
                      onDrop={(e) => {
                        e.preventDefault();
                        e.currentTarget.classList.remove('border-[#ca1d26]', 'bg-red-50');
                        const files = Array.from(e.dataTransfer.files);
                        if (files.length > 0) {
                          handleFileSelect('cover', files[0]);
                        }
                      }}
                    >
                      <svg className="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                      </svg>
                      <div className="mt-4">
                        <p className="text-sm text-gray-600">
                          <span className="font-medium text-[#ca1d26]">Click to upload</span> or drag and drop
                        </p>
                        <p className="text-xs text-gray-500 mt-1">
                          PNG, JPG, GIF up to 5MB
                        </p>
                      </div>
                    </div>
                  ) : fileUploads.cover.uploading ? (
                    <div className="border border-gray-300 rounded-lg p-4">
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium text-gray-700">
                          {fileUploads.cover.file?.name}
                        </span>
                        <span className="text-sm text-gray-500">
                          {Math.round(fileUploads.cover.progress)}%
                        </span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2">
                        <div
                          className="bg-[#ca1d26] h-2 rounded-full transition-all duration-300"
                          style={{ width: `${fileUploads.cover.progress}%` }}
                        ></div>
                      </div>
                      <p className="text-xs text-gray-500 mt-1">Uploading...</p>
                    </div>
                  ) : fileUploads.cover.uploaded ? (
                    <div className="border border-green-300 bg-green-50 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                          {fileUploads.cover.preview && (
                            <img
                              src={fileUploads.cover.preview}
                              alt="Cover preview"
                              className="h-12 w-12 object-cover rounded"
                            />
                          )}
                          <div>
                            <p className="text-sm font-medium text-gray-700">
                              {fileUploads.cover.file?.name}
                            </p>
                            <p className="text-xs text-green-600">Upload complete</p>
                          </div>
                        </div>
                        <button
                          type="button"
                          onClick={() => removeFile('cover')}
                          className="text-red-600 hover:text-red-800"
                        >
                          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="border border-gray-300 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                          {fileUploads.cover.preview && (
                            <img
                              src={fileUploads.cover.preview}
                              alt="Preview"
                              className="h-12 w-12 object-cover rounded"
                            />
                          )}
                          <div>
                            <p className="text-sm font-medium text-gray-700">
                              {fileUploads.cover.file?.name}
                            </p>
                            <p className="text-xs text-gray-500">Ready to upload</p>
                          </div>
                        </div>
                        <div className="flex space-x-2">
                          <button
                            type="button"
                            onClick={() => uploadFile('cover')}
                            className="px-3 py-1 text-sm bg-[#ca1d26] text-white rounded hover:bg-red-700"
                          >
                            Upload
                          </button>
                          <button
                            type="button"
                            onClick={() => removeFile('cover')}
                            className="text-red-600 hover:text-red-800"
                          >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                            </button>
                        </div>
                      </div>
                    </div>
                  )}

                  <input
                    id="cover-upload"
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) {
                        handleFileSelect('cover', file);
                      }
                    }}
                  />
                </div>
              </div>
            </div>

            {/* Manual URL Override Section */}
            <div className="border-t border-gray-200 pt-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Manual URL Override (Optional)</h3>
              <p className="text-sm text-gray-600 mb-4">
                If you prefer to use external URLs instead of uploading files, you can specify them below.
              </p>

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
            </div>

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

            {/* Enhanced Preview */}
            {(formData.coverImage || fileUploads.cover.preview || formData.title) && (
              <div className="border rounded-lg p-4 bg-gray-50">
                <h3 className="text-sm font-medium text-gray-700 mb-3">Book Preview</h3>
                <div className="flex items-start space-x-4">
                  <div className="flex-shrink-0">
                    {(fileUploads.cover.preview || formData.coverImage) ? (
                      <img
                        src={fileUploads.cover.preview || formData.coverImage}
                        alt="Cover preview"
                        className="w-16 h-20 object-cover rounded shadow-sm"
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
                    <div className="flex flex-wrap gap-2 mt-2">
                      {formData.subject && (
                        <span className="inline-block px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                          {formData.subject}
                        </span>
                      )}
                      {formData.semester && (
                        <span className="inline-block px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                          {formData.semester}
                        </span>
                      )}
                      {formData.academicYear && (
                        <span className="inline-block px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                          {formData.academicYear}
                        </span>
                      )}
                    </div>
                    {(fileUploads.pdf.uploaded || formData.pdfUrl) && (
                      <div className="flex items-center mt-2 text-xs text-green-600">
                        <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                        PDF Available
                      </div>
                    )}
                    {formData.title && formData.subject && formData.semester && formData.academicYear && (
                      <div className="mt-2 text-xs text-gray-500">
                        <span className="font-medium">Storage Path:</span><br />
                        {formData.academicYear}/{formData.semester.replace(/\s+/g, '_')}/{formData.subject.replace(/\s+/g, '_')}/{formData.title.replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, '_')}/
                      </div>
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