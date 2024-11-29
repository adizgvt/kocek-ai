var Clipper = require('image-clipper');
 
Clipper('/path/to/image.jpg', function() {
    this.crop(20, 20, 100, 100)
    .resize(50, 50)
    .quality(80)
    .toFile('/path/to/result.jpg', function() {
       console.log('saved!');
   });
});