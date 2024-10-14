<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;

/**
 * Represents an HTTP Status
 */
class HttpStatus {
    // Informative responses
    const HTTP_CONTINUE = 100;
    const HTTP_SWITCHING_PROTOCOLS = 101;
    const HTTP_PROCESSING = 102;            // RFC2518
    const HTTP_EARLY_HINTS = 103;           // RFC8297
    // Successfull responses
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    const HTTP_NO_CONTENT = 204;
    const HTTP_RESET_CONTENT = 205;
    const HTTP_PARTIAL_CONTENT = 206;
    const HTTP_MULTI_STATUS = 207;          // RFC4918
    const HTTP_ALREADY_REPORTED = 208;      // RFC5842
    const HTTP_IM_USED = 226;               // RFC3229
    // Redirects
    const HTTP_MULTIPLE_CHOICES = 300;
    const HTTP_MOVED_PERMANENTLY = 301;
    const HTTP_FOUND = 302;
    const HTTP_SEE_OTHER = 303;
    const HTTP_NOT_MODIFIED = 304;
    const HTTP_USE_PROXY = 305;
    const HTTP_RESERVED = 306;
    const HTTP_TEMPORARY_REDIRECT = 307;
    const HTTP_PERMANENTLY_REDIRECT = 308;  // RFC7238
    // Client Errors (Bad Requests)
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_PAYMENT_REQUIRED = 402;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_NOT_ACCEPTABLE = 406;
    const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_REQUEST_TIMEOUT = 408;
    const HTTP_CONFLICT = 409;
    const HTTP_GONE = 410;
    const HTTP_LENGTH_REQUIRED = 411;
    const HTTP_PRECONDITION_FAILED = 412;
    const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    const HTTP_REQUEST_URI_TOO_LONG = 414;
    const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const HTTP_EXPECTATION_FAILED = 417;
    const HTTP_MISDIRECTED_REQUEST = 421;                                         // RFC7540
    const HTTP_UNPROCESSABLE_ENTITY = 422;                                        // RFC4918
    const HTTP_LOCKED = 423;                                                      // RFC4918
    const HTTP_FAILED_DEPENDENCY = 424;                                           // RFC4918
    const HTTP_TOO_EARLY = 425;                                                   // RFC-ietf-httpbis-replay-04
    const HTTP_UPGRADE_REQUIRED = 426;                                            // RFC2817
    const HTTP_PRECONDITION_REQUIRED = 428;                                       // RFC6585
    const HTTP_TOO_MANY_REQUESTS = 429;                                           // RFC6585
    const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;                             // RFC6585
    const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    // Server Errors
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_NOT_IMPLEMENTED = 501;
    const HTTP_BAD_GATEWAY = 502;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;                        // RFC2295
    const HTTP_INSUFFICIENT_STORAGE = 507;                                        // RFC4918
    const HTTP_LOOP_DETECTED = 508;                                               // RFC5842
    const HTTP_NOT_EXTENDED = 510;                                                // RFC2774
    const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;

    /**
     * HTTP Status Codes
     * 
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     */
    private $http_status = [
        // Informative responses
        100 => 'Continue',	                        //[RFC7231, Section 6.2.1]
        101	=> 'Switching Protocols',	            //[RFC7231, Section 6.2.2]
        102	=> 'Processing',	                    //[RFC2518]
        103	=> 'Early Hints',	                    //[RFC8297]
        // Successfull responses
        200 => 'OK',	                            //[RFC7231, Section 6.3.1]
        201 => 'Created',	                        //[RFC7231, Section 6.3.2]
        202 => 'Accepted',	                        //[RFC7231, Section 6.3.3]
        203 => 'Non-Authoritative Information',	    //[RFC7231, Section 6.3.4]
        204 => 'No Content',	                    //[RFC7231, Section 6.3.5]
        205 => 'Reset Content',	                    //[RFC7231, Section 6.3.6]
        206 => 'Partial Content',	                //[RFC7233, Section 4.1]
        207 => 'Multi-Status',	                    //[RFC4918]
        208 => 'Already Reported',	                //[RFC5842]
        226	=> 'IM Used',	                        //[RFC3229]
        // Redirects
        300 => 'Multiple Choices',	                //[RFC7231, Section 6.4.1]
        301 => 'Moved Permanently',	                //[RFC7231, Section 6.4.2]
        302 => 'Found',	                            //[RFC7231, Section 6.4.3]
        303 => 'See Other',	                        //[RFC7231, Section 6.4.4]
        304 => 'Not Modified',	                    //[RFC7232, Section 4.1]
        305 => 'Use Proxy',	                        //[RFC7231, Section 6.4.5]
        306 => '(Unused)',	                        //[RFC7231, Section 6.4.6]
        307 => 'Temporary Redirect',	            //[RFC7231, Section 6.4.7]
        308 => 'Permanent Redirect',	            //[RFC7538]
        // Client Errors (Bad Requests)
        400 => 'Bad Request',	                    //[RFC7231, Section 6.5.1]
        401 => 'Unauthorized',	                    //[RFC7235, Section 3.1]
        402 => 'Payment Required',	                //[RFC7231, Section 6.5.2]
        403 => 'Forbidden',	                        //[RFC7231, Section 6.5.3]
        404 => 'Not Found',	                        //[RFC7231, Section 6.5.4]
        405 => 'Method Not Allowed',	            //[RFC7231, Section 6.5.5]
        406 => 'Not Acceptable',	                //[RFC7231, Section 6.5.6]
        407 => 'Proxy Authentication Required',	    //[RFC7235, Section 3.2]
        408 => 'Request Timeout',	                //[RFC7231, Section 6.5.7]
        409 => 'Conflict',	                        //[RFC7231, Section 6.5.8]
        410 => 'Gone',	                            //[RFC7231, Section 6.5.9]
        411 => 'Length Required',	                //[RFC7231, Section 6.5.10]
        412 => 'Precondition Failed',	            //[RFC7232, Section 4.2][RFC8144, Section 3.2]
        413 => 'Payload Too Large',	                //[RFC7231, Section 6.5.11]
        414 => 'URI Too Long',	                    //[RFC7231, Section 6.5.12]
        415 => 'Unsupported Media Type',	        //[RFC7231, Section 6.5.13][RFC7694, Section 3]
        416 => 'Range Not Satisfiable',	            //[RFC7233, Section 4.4]
        417 => 'Expectation Failed',	            //[RFC7231, Section 6.5.14]
        421 => 'Misdirected Request',	            //[RFC7540, Section 9.1.2]
        422 => 'Unprocessable Entity',	            //[RFC4918]
        423 => 'Locked',	                        //[RFC4918]
        424 => 'Failed Dependency',	                //[RFC4918]
        425 => 'Too Early',	                        //[RFC8470]
        426 => 'Upgrade Required',	                //[RFC7231, Section 6.5.15]
        428 => 'Precondition Required',	            //[RFC6585]
        429 => 'Too Many Requests',	                //[RFC6585]
        431 => 'Request Header Fields Too Large',	//[RFC6585]
        451 => 'Unavailable For Legal Reasons',	    //[RFC7725]
        // Server Errors
        500 => 'Internal Server Error',	            //[RFC7231, Section 6.6.1]
        501 => 'Not Implemented',	                //[RFC7231, Section 6.6.2]
        502 => 'Bad Gateway',	                    //[RFC7231, Section 6.6.3]
        503 => 'Service Unavailable',	            //[RFC7231, Section 6.6.4]
        504 => 'Gateway Timeout',	                //[RFC7231, Section 6.6.5]
        505 => 'HTTP Version Not Supported',	    //[RFC7231, Section 6.6.6]
        506 => 'Variant Also Negotiates',	        //[RFC2295]
        507 => 'Insufficient Storage',	            //[RFC4918]
        508 => 'Loop Detected',	                    //[RFC5842]
        510 => 'Not Extended',	                    //[RFC2774]
        511 => 'Network Authentication Required'	//[RFC6585]
    ];

    /**
     * The setted status code
     * 
     * @var int
     */
    private $status_code;

    /**
     * Initialize the status
     * 
     * @param int $status_code The http status code
     * @throws InvalidArgumentException
     */
    public function __construct(int $status_code) {
        if(!in_array($status_code, array_keys($this->http_status))) {
            throw new InvalidArgumentException(sprintf('The code %d is not a valid HTTP status code. View "http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml" for more info.', $status_code));
        }

        $this->status_code = $status_code;
    }

    /**
     * Send the HTTP status header
     * 
     * @return void
     */
    public function sendHttpStatus(): void {
        http_response_code($this->status_code);
    }
}

?>