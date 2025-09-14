import type { Book } from '../types';

// Helper function to clean and extract book title from filename
const extractTitleFromFilename = (filename: string): string => {
  // Remove .pdf extension
  let title = filename.replace('.pdf', '');

  // Extract title before first " -- " if it exists (many files have author after --)
  if (title.includes(' -- ')) {
    title = title.split(' -- ')[0];
  }

  // Clean up common patterns
  title = title
    .replace(/_/g, ' ')                    // Replace underscores with spaces
    .replace(/\s+/g, ' ')                  // Replace multiple spaces with single space
    .replace(/^\d+\.\s*/, '')              // Remove leading numbers like "1. "
    .replace(/\([^)]*\)/g, '')             // Remove content in parentheses
    .trim();

  // Capitalize first letter of each word
  return title.split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ');
};

// Helper function to extract author from filename
const extractAuthorFromFilename = (filename: string): string => {
  const parts = filename.split(' -- ');
  if (parts.length >= 2) {
    let author = parts[1];
    // Clean up author name
    author = author
      .replace(/[_,]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    // If it looks like "Last, First", convert to "First Last"
    if (author.includes(',') && author.split(',').length === 2) {
      const [last, first] = author.split(',').map(s => s.trim());
      author = `${first} ${last}`;
    }

    return author;
  }

  return 'Unknown Author';
};

// Helper function to determine subject from filename/title
const determineSubject = (title: string, filename: string): string => {
  const text = (title + ' ' + filename).toLowerCase();

  // Define subject keywords
  const subjects: Record<string, string[]> = {
    'Computer Science': ['computer', 'programming', 'software', 'python', 'java', 'data science', 'algorithm', 'coding', 'systems analysis', 'cryptography'],
    'Business': ['business', 'management', 'marketing', 'entrepreneurship', 'operations', 'international business', 'consumer behavior', 'social media marketing'],
    'Mathematics': ['mathematics', 'math', 'calculus', 'algebra', 'statistics', 'contemporary business mathematics'],
    'Psychology': ['psychology', 'abnormal psychology', 'positive psychology', 'counseling', 'psychotherapy'],
    'Hospitality': ['hotel', 'hospitality', 'food handler', 'servsafe', 'customer service', 'check-in check-out'],
    'Art & Design': ['art', 'drawing', 'fashion', 'illustration', 'visual', 'design', 'pencil to pen'],
    'Media & Communication': ['advertising', 'video production', 'digital marketing', 'media'],
    'Education': ['internship', 'practicum', 'field placement', 'juvenile delinquency'],
    'Cross-Cultural Studies': ['cross-cultural', 'management', 'international']
  };

  for (const [subject, keywords] of Object.entries(subjects)) {
    if (keywords.some(keyword => text.includes(keyword))) {
      return subject;
    }
  }

  return 'General Studies';
};

// Generate book description based on title and subject
const generateDescription = (title: string, author: string, subject: string): string => {
  const descriptions: Record<string, string> = {
    'Computer Science': `Comprehensive guide covering fundamental concepts in computer science. This resource provides in-depth coverage of key topics essential for students and professionals in the field.`,
    'Business': `Essential business concepts and practical applications for modern organizations. Covers strategic thinking, management principles, and real-world case studies.`,
    'Mathematics': `Mathematical foundations and problem-solving techniques. Includes theoretical concepts with practical applications and examples.`,
    'Psychology': `Exploration of human behavior, mental processes, and psychological principles. Combines theoretical frameworks with practical insights.`,
    'Hospitality': `Professional guidance for hospitality industry operations. Covers best practices, standards, and practical applications in hospitality management.`,
    'Art & Design': `Creative techniques and artistic principles for visual communication. Includes step-by-step guides and professional insights.`,
    'Media & Communication': `Modern approaches to media production and communication strategies. Covers digital tools, techniques, and industry best practices.`,
    'Education': `Educational resources and professional development materials. Practical guidance for learning and skill development.`,
    'Cross-Cultural Studies': `Understanding cultural dynamics in global contexts. Explores cross-cultural communication and management strategies.`,
    'General Studies': `Academic resource covering important concepts and principles. Provides comprehensive coverage of essential topics.`
  };

  return descriptions[subject] || descriptions['General Studies'];
};

// Main function to generate book data from PDF files
export const generateBooksFromPDFs = (): Book[] => {
  // List of actual PDF files in the public/books directory
  const pdfFiles = [
    'Abnormal Psychology, 8th Ed -- Susan Nolen-Hoeksema -- 8, 2020 -- Mcgraw hill Education -- 9781260080469 -- 1ad3b6fe76c3d49f594688cc990714eb -- Anna\'s Archive.pdf',
    'Advertising & IMC_ Principles and Practice (11th Edition) -- Sandra Moriarty, Nancy Mitchell, Charles Wood -- 11, 2018-02-18 -- Pearson Education -- 9780134450629 -- d36ed937e4458b1cd1989f0bace9b9bf -- Anna\'s Archiv.pdf',
    'Advertising shits in your head _ strategies for resistance -- Bonner, Matt;Raoul, Vyvian -- Toronto, Ontario, Canada, 2019 -- PM Press -- 9781629635743 -- 503840836719a34ecef3a5d0b32acd0d -- Anna.pdf',
    'Art - a visual history (Scanned) -- Robert Cumming -- American ; Revised edition, New York, New York, 2015 -- Dorling Kindersley Publishing, -- 9780241186107 -- 1212113aa640e34889c7abc26ec78146 -- Anna\'s Archive.pdf',
    'Check-In Check-Out Managing Hotel Operations.pdf',
    'Computer organization and architecture _ designing for -- William Stallings -- 10_ ed, Boston, 2016.pdf',
    'Consumer behavior _ buying, having, and being -- Michael R_ Solomon -- 13th edition, 2019 -- Pearson Education, Limited -- 9780135200131 -- 1943fb3292c2a9f53e30bf9fa0e03964 -- Anna\'s Archive.pdf',
    'Contemporary Business Mathematics for Colleges, 15th edition -- James E_ Deitz, James L_ Southam -- 15th ed, Mason, OH, ©2008 -- South-Western College -- 9780324595451 -- d8cd50fa28d2763bbcd720f6fff86c7b -- Anna\'s A.pdf',
    'Counseling and psychotherapy theories in context and -- John Sommers-Flanagan, Rita Sommers-Flanagan, Chelsea Bodnar -- 2, 2012 -- Wiley & Sons, -- 9780470904374 -- cfc003768a8df60b5f28aa7bae9ac5c7 -- Anna\'s Archive.pdf',
    'Cryptography_and_Network_Security.pdf',
    'Customer Service A Practical Approach.pdf',
    'Digital Marketing -- Dave Chaffey & Fiona Ellis-Chadwick [Chaffey, Dave] -- Feb 07, 2019 -- Pearson Education Limited -- 9781292241579 -- 0b8eaca67c4ecc21274bda6b5afe94bb -- Anna\'s Archive.pdf',
    'Entrepreneurship_ Successfully Launching New Ventures -- Bruce R_ Barringer, R_ Duane Ireland, Bruce Barringer, Duane -- 6, 2018-01-16 -- Pearson -- 9780134729534 -- 08fe344149eed773daf58e63794c73bb -- Anna\'s Archiv.pdf',
    'From Pencil to Pen Tool - Understanding and Creating Digital Fashion Image.pdf',
    'Great Big Book of Fashion Illustration -- Dawber, Martin -- London, 2011 -- B_T_ Batsford; Batsford -- 9781849940030 -- 6fa7e428037ca0f69f6ca50005b160af -- Anna\'s Archive - Copy.pdf',
    'International Business_ The Challenges of Globalization, -- John J_ Wild and Kenneth L_ Wild -- 10th ed, Harlow, 2023 -- Pearson Education, Limited -- 9780137474714 -- 4b9efe9b13daf8a880ee5264f8afe903 -- Anna\'s Arch.pdf',
    'Intro_to_Python_forComputer_Science_and_Data_Science_Learning_to.pdf',
    'Introduction to Materials Management (6th Edition) -- J_ R_ Tony Arnold, Stephen N_ Chapman, Lloyd M_ Clive -- 6 edition, June 15, 2007 -- Pearson -- 9780132337618 -- 231afaf98e9ca994a54f8d916f1912c5 -- Anna\'s Archi.pdf',
    'Introduction to Video Production _ Studio, Field, and Beyond -- Ronald J_ Compesi, Jaime S_ Gomez -- Taylor & Francis (Unlimited), London, 2016 -- 9780205361076 -- 43f9a67b44f341c28bca0cd98b7936ad -- Anna\'s Archive.pdf',
    'Juvenile Delinquency _ Theory, Research, and the Juvenile -- Peter C_ Kratcoski, Lucille Dunn Kratcoski, Peter -- 6th ed_ 2020, Cham, 2020 -- Springer -- 9783030314514 -- 8a1af2adffd96d29b761ec9ea29a4b82 -- Anna\'s A.pdf',
    'Managerial Accounting for the Hospitality Industry.pdf',
    'NEHA certified professional food handler -- National Environmental Health Association -- NEHA food safety training, Denver, CO, ©2010 -- National -- 9780982014325 -- 854092d88aec437bdbae4f1095a2bac0 -- Anna\'s Archive.pdf',
    'Operations management _ sustainability and supply chain -- Heizer, Jay; Render, Barry; Munson, Chuc.pdf',
    'Positive Psychology_ The Science of Happiness and -- William C_ Compton, Edward L_ Hoffman -- 3, 2020 -- SAGE Publications, Inc; 3rd edition (February -- 9781544322902 -- 5adf68f6145bc3288e274e9918352da3 -- Anna\'s A.pdf',
    'ServSafe Coursebook.pdf',
    'SOCIAL MEDIA MARKETING 2ND EDITION -- TRACY L_TUTEN,MICHAEL R_SOLOMON, Tuten, Tracy L_, Solomon, -- 2015, 2015 -- SAGE Publications Ltd -- 9781446267219 -- 06fd03c05b5ffeb7a20e66c318c45d00 -- Anna\'s Archive.pdf',
    'Systems Analysis and Design_ An Object-Oriented Approach -- Alan Dennis; Barbara Haley Wixom; David.pdf',
    'The Art of Drawing Poses for Beginners_ Techniques for -- Goldman, Ken, Goldman, Stephanie -- Collector\'s Series, 3, 2022 -- Walter Foster Publishing -- 9781600589447 -- 94d484d551cd548 - Copy.pdf',
    'The artist\'s guide to drawing people.pdf',
    'The Internship, Practicum, and Field Placement Handbook.pdf',
    'Understanding Cross-Cultural Management.pdf'
  ];

  return pdfFiles.map((filename, index) => {
    const title = extractTitleFromFilename(filename);
    const author = extractAuthorFromFilename(filename);
    const subject = determineSubject(title, filename);
    const description = generateDescription(title, author, subject);
    const id = (index + 1).toString();
    const now = new Date().toISOString();

    return {
      id,
      title,
      author,
      subject,
      description,
      coverImage: undefined, // No cover images for now
      pdfUrl: `/books/${encodeURIComponent(filename)}`,
      createdAt: now,
      updatedAt: now
    };
  });
};

export const getBooksBySubject = (books: Book[], subject: string): Book[] => {
  return books.filter(book =>
    book.subject.toLowerCase().includes(subject.toLowerCase())
  );
};

export const searchBooks = (books: Book[], query: string): Book[] => {
  const lowercaseQuery = query.toLowerCase();
  return books.filter(book =>
    book.title.toLowerCase().includes(lowercaseQuery) ||
    book.author.toLowerCase().includes(lowercaseQuery) ||
    book.subject.toLowerCase().includes(lowercaseQuery) ||
    book.description?.toLowerCase().includes(lowercaseQuery)
  );
};

export const getBookById = (books: Book[], id: string): Book | undefined => {
  return books.find(book => book.id === id);
};