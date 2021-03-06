
<PRE>FCP FEC Proposal rev. 1.0
<A HREF="mailto:giannijohansson@attbi.com">giannijohansson@attbi.com</A> 20020912

I. INTRODUCTION:

This proposal presents a set of new FCP commands that can be used to
encode and decode files using forward error correction (FEC).

FEC is a way of encoding packetized data files with extra error
recovery information which can be used to reconstruct lost packets.
In this document I will refer to the packets containing data as &quot;data
blocks&quot; and those containing error recovery information as &quot;check
blocks&quot;.

One of the objectives of this design is to separate FEC encoding and
decoding from inserting and retrieving the data and check blocks
to/from Freenet.  By separating encoding and decoding from insertion
and retrieval I sidestep the problem of having to hold FCP connections
open while waiting for large amounts of data to be fetched from /
inserted into Freenet.

For a given maximum block size, some FEC algorithms can only
practically handle files up to a certain maximum size.  The design
uses segmentation to handle this case.  Large files are divided into
smaller segments and FEC is only done on a per segment basis.  This
compromise provides a least limited redundancy for large files.

II. Assumptions
This proposal doesn't specify any particular FEC algorithm.  
However the following assumptions are implicit in the design:
 
A. For a given segment with k data blocks and n - k check blocks, it
must be possible to decode all k data blocks from any k of n data or
check blocks.

B. Encoder and decoder implementations must be completely specified by
an implementation name and a file length.  No other parameters can be
required to instantiate the encoder or decoder.

C. Within a segment all data blocks must be the same size and all
check blocks must be the same size.  The check block and data block
sizes are not required to be the same however.  Smaller trailing
blocks must be zero padded to the required length.

D. The encoder may ask for extra trailing data blocks.  These extra
blocks must contain zeros.

II. Proposed FCP FEC commands

convention: All numbers are hexadecimal

A. Helper messages, SegmentHeader and BlockMap

A SegmentHeader message contains all the information necessary to FEC
encode or decode a segment of a file.  SegmentHeaders may contain FEC
implementation specific fields.  They are guaranteed to contain the
documented fields given in the example SegmentHeader
message below:

SegmentHeader
FECAlgorithm=OnionFEC_a_1_2   // The FEC implementation name
FileLength=170000             // Total file length
Offset=0                      // Offset from the start of the file
BlockCount=6                  // Number of data blocks
BlockSize=40000               // Data block size
CheckBlockCount=3             // Number of check blocks
CheckBlockSize=3              // Check block size
Segments=1                    // Total number of segments
SegmentNum=0                  // Index of the current segment
BlocksRequired=6              // Blocks required to decode this segment
EndMessage

Client code should not rely on any undocumented fields.

BlockMap messages are used to list the CHKs of the data and check
blocks for a segment.

Here's an example:

BlockMap
Block.0=freenet:CHK@p2ISvZPkCwbY62xciJb~KrsOCTsSAwI,jGonMeCCz1GCHde5bc1t~w
Block.1=freenet:CHK@1z8CubDNzLEfNfuTYM4NVJAUxU4SAwI,5cxWki4YzWyKP0s3g9~Vow
Block.2=freenet:CHK@~VW7XskmHcJMFlmG6l2c7jkTOnkSAwI,Il2ztTbQImZvVlsnuDq-8Q
Block.3=freenet:CHK@A-qK8GWofXd9JOxb4fHfVMHAUawSAwI,2D5~Mm~MjAfup3edGXy6Eg
Block.4=freenet:CHK@r-FhUu444LxUIUGi5BMuEVGM4nQSAwI,J7HpLvPscLyW3Sc6Nq2S5g
Check.0=freenet:CHK@rLdCwOXO7PAv6BDpm21ThdIwmnkSAwI,4ZX2inJ7gg0EectTxPYRSg
Check.1=freenet:CHK@EjEg1UHWsAfHHMQmRbxe2ToY0RQSAwI,xjJCPsCxpnw9lyNI2VBRGA
EndMessage

B. FECSegmentFile
The FECSegmentFile message is used to generate the segment headers
necessary to encode a file of a given length with a specified FEC
algorithm.  

FECSegmentFile
AlgoName=OnionFEC_a_1_2
FileLength=ABC123
EndMessage

If this command is successful one or more SegmentHeader messages are sent in 
order
of ascending SegmentNumber.

The client can detect when the last segment has been sent by checking the 
SegmentNumber
and Segments field of each received SegmentHeader.

On failure a Failed message is sent.

C. FECEncodeSegment
The FECEncodeSegment message is used to create check blocks for a
segment of a file.  The RequestedList field contains a comma delimited
list of the requested check blocks. If the list is empty or omitted completely
all the check blocks are sent.

The SegmentHeader for the requested segment must sent as data in the
trailing field of the FECEncodeSegment message, preceding the raw
segment data to encode.

FECEncodeSegment
[RequestedList=0,A,F]
DataLength=&lt;SegmentHeader length&gt; + &lt;segment length&gt;
Data

&lt; SegmentHeader &gt;
&lt; raw data &gt;

If the encode request is successful, the server sends a BlocksEncoded
confirmation message, followed by DataChunk messages for the encoded
blocks.  Check blocks are sent in order of ascending index.

e.g:

BlocksEncoded
BlockCount=3
BlockSize=40000
EndMessage

DataChunk 
...
(3 * 40000 = 0xC0000 worth of DataChunk messages)
...

If the requests fails a Failed message is sent.


Note: 
The total segment size 
(SegmentHeader.BlockSize * SegmentHeader.BlockCount) can exceed the
length of the data present in the last segment.  In this case partial 
blocks should be zero padded and extra zero filled blocks should
be sent if requested.

D. FECDecodeSegment 
The FECDecodeSegment message is used to decode missing data blocks for
a segment of a file.  The RequestedList field contains a comma
delimited list of the requested data blocks.  Similarly, BlockList and
CheckList contain the indices of the data blocks and check blocks that
are being sent to decode from.  All index lists must be in ascending
order.

The SegmentHeader for the segment must sent as data in the trailing
field of the FECDecodeSegment message preceding the blocks to
decode from.

FECDecodeSegment
BlockList=0,2,3,5,6
CheckList=9,c
RequestedList=1,4
DataLength=&lt;SegmentHeader length&gt; + &lt;total length of data and check blocks&gt;
Data
&lt; SegmentHeader &gt;
&lt; raw data blocks in order of index &gt;
&lt; raw check blocks in order of index &gt;

If the decode request is successful, the server sends a BlocksDecoded
confirmation message, followed by DataChunk messages for the decoded
blocks.  The decoded data blocks are sent in order of ascending index.

e.g:

BlocksDecoded
BlockCount=2
BlockSize=40000
DataLength
EndMessage

DataChunk 
...
(2 * 40000 = 0x80000 worth of DataChunk messages)
...

If the requests fails a Failed message is sent.

E. FECSegmentSplitFile

The FECSegmentSplitFile command generates a list of SegmentHeaders and
BlockMaps from SplitFile metadata. The SplitFile metadata should
be sent as data in its trailing field.

e.g.:
FECSegmentSplitFile
DataLength=&lt;SplitFile metadata length&gt;
Data
&lt;SplitFile metadata&gt;

If successful the FCP server sends back one or more pairs of 
SegmentHeader and BlockMap messages in order of ascending segment number.

The client can tell how many pairs are coming by inspecting the Segments
field in the first SegmentHeader.

On failure a Failed message is sent.

F. FECMakeMetadata
The FECMakeMetadata command creates a metadata for a SplitFile from
a list of SegmentHeader BlockMap pairs sent as data in it's trailing field.
The list must be in order of ascending segment number.

FECMakeMetadata
Description=file
MimeType=text/plain
DataLength=&lt;total length of all SegmentHeaders and BlockMaps&gt;
Data
&lt;SegmentHeader, BlockMap pairs&gt;

III. Usage cases
Here's a brief description of how these messages are used to
encode and decode files.

A. Encoding
The client code sends a FECSegmentFile with the file's length and
saves the returned SegmentHeaders.

For each segment, it calls FECEncodeSegment with the segment's data
blocks and saves the returned check blocks.

It then inserts the data and check blocks into Freenet using the
existing ClientPut command, and saves the resulting CHK URIs.

After the data blocks and check blocks for all segments have been
inserted, the client code sends a FECMakeMetadata command with the
SegmentHeaders and BlockMaps listing the inserted blocks.  It saves
the SplitFile metadata and inserts it into Freenet.

Done.

A. Decoding

The client code sends a FECSegmentSplitFile with the SplitFile metadata
and saves the SegmentHeader,BlockMap pairs.

For each segment, it uses the CHK lists int the BlockMap to 
downloads the minimum number of data and check blocks
as given by SegmentHeader.BlocksRequired. It then calls FECDecodeSegment to 
decode the missing data blocks.

Finally, it concatinates the data blocks together, possibly ignoring trailing 
padding blocks.

Done.

III. Changes to SplitFile metadata format.

0) Deprecate the BlockSize field, since check blocks are not necessarily the
same size as data blocks and blocks may be different sizes across segments.

1) Add an AlgoName field. This is the name for the decoder and encoder 
implementation,
that can be used to decode or re-encode the file.  This replaces decoder.name 
and decoder.encoder
in the previous implementation.

2) Remove the decoder FieldSet.

* SplitFile.Graph is currently not being used and is not implemented.

</PRE>
