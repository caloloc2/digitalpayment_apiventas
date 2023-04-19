<?php 
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class AWSS3{
    private $options = [
        'profile' => 'default',
        'region' => 'us-west-2',
        'version' => 'latest'
    ];
 
    function create_bucket($bucketName){
        $retorno = array("estado" => false);

        try {
            $s3Client = new S3Client($this->options);

            $result = $s3Client->createBucket([
                'Bucket' => $bucketName,
            ]);

            $retorno['s3'] = $result;
            $retorno['estado'] = true;
        } catch (AwsException $e) {
            $retorno['error'] = $e->getMessage();            
        }

        return $retorno;
    }

    function saveFileS3($file, $file_path, $bucket){
        $retorno = array("estado" => false);

        try {
            $s3Client = new S3Client($this->options);                    

            $result = $s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $file_path,
                'SourceFile' => $file,
            ]);
                        
            $retorno['s3'] = json_decode($result, true);
            $retorno['estado'] = true;
            
        } catch (Exception $e) {
            $retorno['error'] = $e->getMessage();
        } catch (S3Exception $e) {
            $retorno['error'] = $e->getMessage();
        } catch (AwsException $e){
            $retorno['error'] = array(
                "requestId" => $e->getAwsRequestId()
            );
        }

        return $retorno;
    }    

    function get_url_file($bucket, $file_Path, $timelife){
        $retorno = array("estado" => false);

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

            $retorno['s3'] = $presignedUrl;
            $retorno['estado'] = true;

        } catch (S3Exception $e) {
            $retorno['error'] = $e->getMessage();            
        }

        return $retorno;
    }

    function remove_file_to_bucket($file_Path, $bucket){
        $retorno = array("estado" => false);

        try {
            $s3Client = new S3Client($this->options);

            $result = $s3Client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $file_Path
            ]);
            
            $retorno['s3'] = json_decode($result, true);
            $retorno['estado'] = true;
        } catch (S3Exception $e) {
            $retorno['error'] = $e->getMessage();            
        }

        return $retorno;
    }


}
