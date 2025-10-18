# Images Directory

This directory is for storing images used in the Maple House application.

## Missing Images

The following images are referenced in the application but not included:

### 1. elderly-care.jpg
- **Used in**: Homepage about section (`index.php`)
- **Current solution**: CSS placeholder with gradient background and icon
- **Recommended size**: 400x300px or similar aspect ratio
- **Description**: Should show elderly care, nursing home environment, or caring staff with elderly residents

### 2. Hero Background Images (Rotating) - URGENT NEEDED
- **Used in**: Homepage hero section (`index.php`)
- **Files needed**: 
  - `bg-1.jpg` - First background image
  - `bg-2.jpg` - Second background image
  - `bg-3.jpg` - Third background image
- **Recommended size**: 1920x1080px (Full HD) or higher
- **Description**: High-quality images showing:
  - Elderly care facility exterior/interior
  - Happy elderly residents
  - Caring staff with residents
  - Beautiful garden or common areas
- **Note**: Images rotate every 5 seconds with smooth transitions
- **Current solution**: CSS placeholder backgrounds (user wants real images)

## QUICK SETUP - 3 ROTATING IMAGES:

### STEP 1: Download These 3 Images
1. **bg-1.jpg** - Elderly care facility exterior or garden
2. **bg-2.jpg** - Interior common area with residents  
3. **bg-3.jpg** - Staff caring for elderly residents

### STEP 2: Place Images Here
Put all 3 images in: `c:\xampp\htdocs\Maple_House\assets\images\`

### STEP 3: File Names Must Be Exact
- `bg-1.jpg` (exactly this name)
- `bg-2.jpg` (exactly this name)  
- `bg-3.jpg` (exactly this name)

### STEP 4: Refresh Browser
The gray background will be replaced with your rotating images!

## FREE IMAGE SOURCES:

### Option 1: Unsplash (Free, High Quality)
1. Go to: https://unsplash.com/
2. Search for: "elderly care", "nursing home", "senior care"
3. Download 3 different images (1920x1080 or larger)
4. Rename to: bg-1.jpg, bg-2.jpg, bg-3.jpg

### Option 2: Pexels (Free, High Quality)  
1. Go to: https://www.pexels.com/
2. Search for: "elderly care", "senior citizens", "nursing home"
3. Download 3 different images
4. Rename to: bg-1.jpg, bg-2.jpg, bg-3.jpg

## How to Add Images

1. **Download/source appropriate images** (ensure you have proper licensing)
2. **Place images in this directory**: `/assets/images/`
3. **Update the HTML** if you want to replace the placeholder:

```html
<!-- Replace the placeholder div in index.php with: -->
<img src="assets/images/elderly-care.jpg" alt="Elderly Care">
```

## Current Placeholder

The missing `elderly-care.jpg` has been replaced with a CSS-styled placeholder that includes:
- Gradient background matching the site theme
- Heart icon
- "Compassionate Care" text
- Professional appearance

This placeholder will display properly until you add the actual image.

## Image Guidelines

- **Format**: JPG, PNG, or WebP
- **Quality**: High quality but web-optimized
- **Size**: Keep file sizes reasonable for web loading
- **Content**: Professional, caring, appropriate for an elderly care facility
- **Copyright**: Ensure you have rights to use the images
