# Image Placement Guide

Place your images in the `public/` directory with the following names:

## Required Images:

### 1. `public/logo.png`
- **Usage**: Main logo in header (left side)
- **Recommended size**: 300x120px or similar aspect ratio
- **Format**: PNG with transparent background preferred
- **Description**: Your organization/university primary logo
- **Display size**: 64px height (h-16)

### 2. `public/ugc-latest.png`
- **Usage**: UGC logo in header (right side of main logo)
- **Recommended size**: 200x96px or similar aspect ratio
- **Format**: PNG with transparent background preferred
- **Description**: UGC (University Grants Commission) logo
- **Display size**: 48px height (h-12)

### 3. `public/hero-bg.jpg`
- **Usage**: Background image for the public homepage hero section
- **Recommended size**: 1920x1080px or larger
- **Format**: JPG or PNG
- **Description**: Academic/library themed background (books, campus, etc.)
- **Note**: Will be displayed with 20% opacity over the red gradient

### 4. `public/admin-bg.jpg`
- **Usage**: Background image for the admin login page
- **Recommended size**: 1920x1080px or larger  
- **Format**: JPG or PNG
- **Description**: Professional/administrative themed background
- **Note**: Will be displayed with 10% opacity

## Current Image References:

The application is already configured to use these images:

- Main header logo: `/logo.png`
- UGC header logo: `/ugc-latest.png`
- Homepage hero background: `/hero-bg.jpg`
- Admin login background: `/admin-bg.jpg`

## Fallback Behavior:

- If `logo.png` doesn't exist, the text "UCSI DIGITAL LIBRARY Bangladesh Branch" will be shown instead
- If background images don't exist, the pages will still look good with just the gradient backgrounds

## Adding Your Images:

1. Save your images to the `public/` directory with the exact names above
2. No code changes needed - the application will automatically use them
3. Run `npm run dev` to see the changes in development

The application is designed to gracefully handle missing images while providing enhanced visual appeal when they are present.