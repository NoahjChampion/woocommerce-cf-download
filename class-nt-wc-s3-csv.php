<?php
class NT_WC_S3_CSV {
	private $_file;
	
	public $errors = array();
	
	public $access_key;
	public $secret_key;
	
	function __construct() {
		if ( $this->set_csv() ) {
			$this->_stripBOM();
			$this->extract_keys();
		}
	}
	
	/**
	 * Check the uploaded file and set file property.
	 * 
	 * @return boolean CSV was set
	 */
	public function set_csv() {
        
        /**
         * Check a file has been uploaded otherwise output an error message
         */
        if ( empty( $_FILES['amazon_aws_csv_file']['tmp_name'] ) ) {
            $this->errors[] = "Error: No file was detected";
            return false;
        }
        
        /**
         * MIME type checking can be unreliable so initially verify the file extension
         */
        $csv_pathinfo = pathinfo( $_FILES['amazon_aws_csv_file']['name'] );
        
        if ( 'csv' != $csv_pathinfo['extension'] ) {
	        $this->errors[] = "Error: Please upload a valid CSV file";
	        return false;
        }
        
        $this->_file = $_FILES['amazon_aws_csv_file']['tmp_name'];
		
		return true;
    }
	
	/**
     * Delete 'byte order mark' from UTF-8 file.
     *
     * @return bool False if the file couldn't be opened.
     */
    private function _stripBOM() {
        $fname = $this->_file;
        
        $res = fopen( $fname, 'rb' );
        
        if ( false !== $res ) { 
            $bytes = fread( $res, 3 );
            
            if ( $bytes == pack( 'CCC', 0xef, 0xbb, 0xbf ) ) {
               
                fclose( $res );

                $contents = file_get_contents( $fname );
                
                if ( false === $contents ) {
                    trigger_error( 'Failed to get file contents.', E_USER_WARNING );
                }
                
                $contents = substr( $contents, 3 );
                
                $success = file_put_contents( $fname, $contents );
                
                if ( false === $success ) {
                    trigger_error( 'Failed to put file contents.', E_USER_WARNING );
                }
            } else {
                fclose( $res );
            }
            
            return true;  
        } else {  
            $this->errors[] = "Error: Failed to open file.";
            return false;  
        }
    }

	/**
	 * Parse the CSV file and extract the configuration keys
	 * 
	 * @return boolean Keys extracted successfully
	 */
	private function extract_keys() {
		$filename = $this->_file;
		
	    if ( ! file_exists( $filename ) || ! is_readable( $filename ) ) {
			$this->errors[] = "There was an error reading the CSV file.";
	        return false;
		}

		$row = 0;
		
		if ( ( $handle = fopen( $filename, 'r' ) ) !== false ) {
			while ( ( $data = fgetcsv( $handle, 1000, "," ) ) !== false ) {
				$row++;
				
				$split_row = explode( '=', $data[0] );
				
				if ( 1 === $row ) {
					$this->access_key = $split_row[1];
				} else {
					$this->secret_key = $split_row[1];
				}
			}
			
			fclose( $handle );
			
			return true;
		}
		
		return false;
	}
}