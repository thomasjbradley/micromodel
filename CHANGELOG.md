# Changelog

**1.1.0 (Jul. 28, 2012)**

- Added JSON serialization of the model
- Added WHERE clause support to `read()` and `all()`
- Changed the `isValid()` method to return only true/false. Added `getValidationErrors()` method.
- Added CSRF protection flag to the `getForm()` method
- Bug fix: the iterator implementation for current was not returning the value
- Bug fix: determining the table name from the class name did not account for namespaces
- Bug fix: fields were being set twice because of PDO::FETCH_BOTH

**1.0.1 (Jul. 27, 2012)**

- Bug fix: `create()` wasn't setting the primary key
- Bug fix: `register()` wasn't returning the model object
- Internal consistency updates

**1.0.0 (Jul. 26, 2012)**

- Initial Release
