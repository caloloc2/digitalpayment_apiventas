<?php 

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class AWSS3{

    // https://github.com/keithweaver/python-aws-s3
    // https://gist.github.com/keithweaver/70eb06d98b008113ce97f6148fbea83d

    // IAM KEY ACCESS FROM soporte@pymfact.com ACCOUNT from AWS

    private $options = [
        'region' => 'us-west-2',
        'version' => 'latest',
        'credentials' => [
            'key' => 'AKIAWLORTG5GVPHFLNI5',
            'secret' => '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'
        ]
    ];

    public function create_bucket($bucketName){
        try {
            $s3Client = new S3Client($this->options);

            $result = $s3Client->createBucket([
                'Bucket' => $bucketName,
            ]);

            return $result;
        } catch (AwsException $e) {
            return 'Error: ' . $e->getAwsErrorMessage();
        }
    }

    public function saveFileS3($file, $file_path, $bucket){
        try {
            $s3Client = new S3Client($this->options);
            
            // $key = $file; //basename($file_Path);

            $result = $s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $file_path,
                'SourceFile' => $file,
            ]);
            
            return $result;
            
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    public function get_url_file($bucket, $file_Path, $timelife){
        try{
            $s3Client = new S3Client($this->options);

            //Creating a presigned URL
            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $file_Path
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+'.$timelife.' minutes');

            // Get the actual presigned-url
            $presignedUrl = (string)$request->getUri();

            return $presignedUrl;
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    public function remove_file_to_bucket($file_Path, $bucket){
        try {
            $s3Client = new S3Client($this->options);

            $result = $s3Client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $file_Path
            ]);

            return $result;
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
}