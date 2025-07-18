# Daytona TypeScript SDK vs PHP SDK: File Operations Analysis

## Executive Summary

After thoroughly analyzing the official Daytona TypeScript SDK (`libs/sdk-typescript/`) and comparing it with your current PHP SDK implementation, I've identified significant gaps in file operation capabilities. The TypeScript SDK offers far more comprehensive file management functionality than what's currently implemented in your PHP SDK.

## TypeScript SDK File Operations (Complete Feature Set)

### Core FileSystem Class Features

The TypeScript SDK provides a dedicated `FileSystem` class with 12 comprehensive file operations:

#### 1. **createFolder(path, mode)**
- **Purpose**: Create directories with specific permissions
- **API Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/folder`
- **Parameters**: `path` (string), `mode` (octal permissions like "755")
- **Missing in PHP SDK**: ❌ **Not implemented**

#### 2. **deleteFile(path)**
- **Purpose**: Delete files or directories
- **API Endpoint**: `DELETE /toolbox/{sandboxId}/toolbox/files`
- **Status in PHP SDK**: ✅ **Implemented** as `deleteFile()`

#### 3. **downloadFile(remotePath, localPath?, timeout?)**
- **Purpose**: Download files with two modes:
  - Download to Buffer (memory)
  - Download to local file (streaming)
- **API Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/download`
- **Advanced Features**: 
  - Timeout control (default 30 minutes)
  - Streaming for large files
  - Buffer mode for small files
- **Status in PHP SDK**: ⚠️ **Partially implemented** as `readFile()` but missing:
  - Local file saving capability
  - Timeout control
  - Streaming support
  - Buffer return type

#### 4. **findFiles(path, pattern)**
- **Purpose**: Search for text patterns within files (grep-like functionality)
- **API Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/search/content`
- **Returns**: Array of `Match` objects with `file`, `line`, `content`
- **Missing in PHP SDK**: ❌ **Not implemented**

#### 5. **getFileDetails(path)**
- **Purpose**: Get comprehensive file metadata
- **API Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/info`
- **Returns**: Complete `FileInfo` with metadata
- **Status in PHP SDK**: ⚠️ **Basic implementation** via `fileExists()` but missing detailed metadata

#### 6. **listFiles(path)**
- **Purpose**: List directory contents
- **API Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files`
- **Status in PHP SDK**: ✅ **Implemented** as `listDirectory()`

#### 7. **moveFiles(source, destination)**
- **Purpose**: Move/rename files and directories
- **API Endpoint**: `PUT /toolbox/{sandboxId}/toolbox/files/move`
- **Missing in PHP SDK**: ❌ **Not implemented**

#### 8. **replaceInFiles(files[], pattern, newValue)**
- **Purpose**: Find and replace text across multiple files
- **API Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/replace`
- **Parameters**: Array of file paths, search pattern, replacement text
- **Returns**: Array of `ReplaceResult` objects with success/error status per file
- **Missing in PHP SDK**: ❌ **Not implemented**

#### 9. **searchFiles(path, pattern)**
- **Purpose**: Search for files by name pattern (find-like functionality)
- **API Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/search`
- **Parameters**: Directory path, file name pattern (supports globs)
- **Returns**: `SearchFilesResponse` with array of matching file paths
- **Missing in PHP SDK**: ❌ **Not implemented**

#### 10. **setFilePermissions(path, permissions)**
- **Purpose**: Set file/directory permissions and ownership
- **API Endpoint**: `PUT /toolbox/{sandboxId}/toolbox/files/permissions`
- **Parameters**: Path, `FilePermissionsParams` (mode, owner, group)
- **Missing in PHP SDK**: ❌ **Not implemented**

#### 11. **uploadFile(source, remotePath, timeout?)**
- **Purpose**: Upload files with two modes:
  - Upload from Buffer (memory)
  - Upload from local file path (streaming)
- **API Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/upload`
- **Advanced Features**:
  - Timeout control
  - Streaming for large files
  - Buffer support for in-memory content
- **Status in PHP SDK**: ⚠️ **Partially implemented** as `writeFile()` but missing:
  - Local file upload capability
  - Timeout control
  - Streaming support

#### 12. **uploadFiles(files[], timeout?)**
- **Purpose**: Batch upload multiple files
- **API Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/upload/batch`
- **Parameters**: Array of `FileUpload` objects
- **Advanced Features**: Multi-file upload in single request
- **Missing in PHP SDK**: ❌ **Not implemented**

## Data Structure Comparisons

### FileInfo Structure

**TypeScript SDK FileInfo:**
```typescript
interface FileInfo {
  name: string
  isDir: boolean
  size: number
  modTime: string      // ISO date string
  mode: string         // Octal permissions
  permissions: string  // Human-readable permissions
  owner: string        // File owner
  group: string        // File group
}
```

**PHP SDK FileInfo:**
```php
class FileInfo {
  public readonly string $name;
  public readonly string $path;           // Extra: full path
  public readonly bool $isDirectory;      // Same as isDir
  public readonly ?int $size;             // Optional vs required
  public readonly ?string $modifiedAt;    // Optional vs required
  public readonly ?string $permissions;   // Optional, less detailed
  // Missing: mode, owner, group
}
```

**Gaps in PHP SDK FileInfo:**
- Missing `mode` (octal permissions)
- Missing `owner` and `group` information
- `modTime` is optional instead of required
- Less standardized field naming

### Additional TypeScript SDK Types

**Match (for search results):**
```typescript
interface Match {
  file: string    // File path
  line: number    // Line number where match found
  content: string // Content of the matching line
}
```

**ReplaceRequest/ReplaceResult:**
```typescript
interface ReplaceRequest {
  files: Array<string>  // File paths to process
  pattern: string       // Search pattern
  newValue: string      // Replacement text
}

interface ReplaceResult {
  file?: string        // File that was processed
  success?: boolean    // Whether replacement succeeded
  error?: string       // Error message if failed
}
```

**SearchFilesResponse:**
```typescript
interface SearchFilesResponse {
  files: Array<string>  // Array of matching file paths
}
```

**FilePermissionsParams:**
```typescript
interface FilePermissionsParams {
  mode?: string   // Octal permissions (e.g., "644")
  owner?: string  // User owner
  group?: string  // Group owner
}
```

## Missing API Capabilities in PHP SDK

### 1. **Advanced File Operations**
- ❌ Directory creation with permissions
- ❌ File/directory moving/renaming
- ❌ Permission and ownership management
- ❌ File pattern searching
- ❌ Content searching within files
- ❌ Multi-file text replacement

### 2. **Batch Operations**
- ❌ Multi-file upload
- ❌ Bulk text replacement across files

### 3. **Advanced Upload/Download**
- ❌ Streaming file transfers for large files
- ❌ Timeout control for long operations
- ❌ Local file system integration (save to/upload from local paths)

### 4. **Search and Discovery**
- ❌ Find files by name pattern
- ❌ Search text content within files
- ❌ Structured search results with line numbers

### 5. **File Metadata**
- ❌ Complete file permissions (octal mode)
- ❌ File ownership information
- ❌ Standardized timestamp handling

## Recommended Implementation Priority

### High Priority (Core Missing Features)
1. **Directory Management**
   - `createFolder(string $path, string $mode)`
   - `moveFile(string $source, string $destination)`

2. **Enhanced File Information**
   - Update `FileInfo` DTO to include `mode`, `owner`, `group`
   - Add `getFileDetails(string $path)` method

3. **File Permissions**
   - `setFilePermissions(string $path, array $permissions)`

### Medium Priority (Power User Features)
4. **Search Operations**
   - `searchFiles(string $path, string $pattern)` (find by filename)
   - `findInFiles(string $path, string $pattern)` (grep functionality)

5. **Text Operations**
   - `replaceInFiles(array $files, string $pattern, string $newValue)`

### Lower Priority (Advanced Features)
6. **Batch Operations**
   - `uploadFiles(array $files, ?int $timeout = null)`

7. **Enhanced Upload/Download**
   - Update existing methods to support local file paths
   - Add timeout parameters
   - Add streaming support for large files

## Complete API Specification

### 1. **createFolder**
- **Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/folder`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - Directory path to create
  - `mode` (string, required) - Octal permissions (e.g., "755")
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `void` (success/error only)

### 2. **deleteFile**
- **Endpoint**: `DELETE /toolbox/{sandboxId}/toolbox/files`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - File/directory path to delete
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `void` (success/error only)

### 3. **downloadFile**
- **Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/download`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - File path to download
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `File` (binary file content)

### 4. **findInFiles**
- **Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/find`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - Directory to search in
  - `pattern` (string, required) - Text pattern to search for
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `Array<Match>` where `Match` contains:
  ```typescript
  {
    file: string,    // File path where match found
    line: number,    // Line number
    content: string  // Content of the matching line
  }
  ```

### 5. **getFileInfo**
- **Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/info`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - File path to get info for
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `FileInfo` object:
  ```typescript
  {
    name: string,         // File name
    isDir: boolean,       // Is directory
    size: number,         // File size in bytes
    modTime: string,      // ISO date string
    mode: string,         // Octal permissions
    permissions: string,  // Human-readable permissions
    owner: string,        // File owner
    group: string         // File group
  }
  ```

### 6. **listFiles**
- **Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, optional) - Directory path to list
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `Array<FileInfo>` (array of FileInfo objects)

### 7. **moveFile**
- **Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/move`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `source` (string, required) - Source path
  - `destination` (string, required) - Destination path
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `void` (success/error only)

### 8. **replaceInFiles**
- **Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/replace`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `replaceRequest` (object, required) - Replace request body:
    ```typescript
    {
      files: Array<string>,  // File paths to process
      pattern: string,       // Search pattern
      newValue: string       // Replacement text
    }
    ```
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `Array<ReplaceResult>` where `ReplaceResult` contains:
  ```typescript
  {
    file?: string,     // File that was processed
    success?: boolean, // Whether replacement succeeded
    error?: string     // Error message if failed
  }
  ```

### 9. **searchFiles**
- **Endpoint**: `GET /toolbox/{sandboxId}/toolbox/files/search`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - Directory to search in
  - `pattern` (string, required) - File name pattern (supports globs)
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `SearchFilesResponse`:
  ```typescript
  {
    files: Array<string>  // Array of matching file paths
  }
  ```

### 10. **setFilePermissions**
- **Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/permissions`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - File path to set permissions for
  - `owner` (string, optional) - User owner
  - `group` (string, optional) - Group owner
  - `mode` (string, optional) - Octal permissions (e.g., "644")
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `void` (success/error only)

### 11. **uploadFile**
- **Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/upload`
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `path` (string, required) - Destination path in sandbox
  - `file` (File, optional) - File to upload (multipart form data)
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
- **Returns**: `void` (success/error only)
- **Note**: This method is marked as `@deprecated` in the API

### 12. **uploadFiles**
- **Endpoint**: `POST /toolbox/{sandboxId}/toolbox/files/upload` (batch)
- **Parameters**:
  - `sandboxId` (string, required) - Sandbox ID
  - `X-Daytona-Organization-ID` (header, optional) - Organization ID
  - Form data with multiple files
- **Returns**: `void` (success/error only)

## Implementation Notes

### Authentication
All endpoints require Bearer token authentication and optionally support organization-specific requests via the `X-Daytona-Organization-ID` header.

### Error Handling
The TypeScript SDK uses structured error responses. Your PHP SDK should implement similar error handling for the new operations.

### Path Handling
The TypeScript SDK has sophisticated relative path resolution. Consider implementing similar path utilities in your PHP SDK.

## Conclusion

Your current PHP SDK implements approximately **25%** of the file operation capabilities available in the official TypeScript SDK. The biggest gaps are:

1. **Directory management** (creation, permissions)
2. **File search capabilities** (by name and content)
3. **Text manipulation** (find/replace across files)
4. **Advanced file metadata** (permissions, ownership)
5. **File operations** (move/rename)

The TypeScript SDK reveals that Daytona's API is actually quite comprehensive for file operations, but your current PHP implementation only scratches the surface of what's available.